<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\Notification\Enums\SmsStatus;
use Modules\Notification\Exceptions\OrangeSmsException;
use Modules\Notification\Jobs\SendSmsJob;
use Modules\Notification\Models\SmsLog;
use Modules\Notification\Services\SmsService;

beforeEach(function (): void {
    Cache::flush();

    config([
        'notification.orange.base_url' => 'https://orange-sms.test',
        'notification.orange.oauth_token_path' => '/oauth/v3/token',
        'notification.orange.sms_send_path_template' => '/smsmessaging/v1/outbound/tel%3A%2B{sender}/requests',
        'notification.orange.client_id' => 'sms-client',
        'notification.orange.client_secret' => 'sms-secret',
        'notification.orange.sender_number' => '224621000001',
        'notification.orange.sender_name' => 'Kilora',
        'notification.orange.oauth_cache_key' => 'notification.orange.oauth_token.test',
    ]);
});

it('dispatches send sms job on notifications queue when async', function (): void {
    Queue::fake();

    app(SmsService::class)->send('224621234567', 'Async message', async: true);

    $log = SmsLog::query()->sole();

    expect($log->status)->toBe(SmsStatus::Pending)
        ->and($log->recipient)->toBe('224621234567');

    Queue::assertPushedOn('notifications', SendSmsJob::class);
});

it('sends sms synchronously and marks log as sent', function (): void {
    Http::fake([
        'https://orange-sms.test/oauth/v3/token' => Http::response([
            'access_token' => 'sms-token-sync',
            'expires_in' => 3600,
        ], 200),
        'https://orange-sms.test/smsmessaging/v1/outbound/tel%3A%2B224621000001/requests' => Http::response([], 201),
    ]);

    app(SmsService::class)->send('224621234567', 'Sync message', async: false);

    $log = SmsLog::query()->sole();

    expect($log->status)->toBe(SmsStatus::Sent)
        ->and($log->sent_at)->not->toBeNull();
});

it('marks log failed on synchronous provider error', function (): void {
    Http::fake([
        'https://orange-sms.test/oauth/v3/token' => Http::response([
            'access_token' => 'sms-token-sync',
            'expires_in' => 3600,
        ], 200),
        'https://orange-sms.test/smsmessaging/v1/outbound/tel%3A%2B224621000001/requests' => Http::response(['error' => 'fail'], 500),
    ]);

    expect(fn () => app(SmsService::class)->send('224621234567', 'Fail sync', async: false))
        ->toThrow(OrangeSmsException::class);

    $log = SmsLog::query()->sole();

    expect($log->status)->toBe(SmsStatus::Failed)
        ->and($log->error_message)->not->toBeEmpty();
});

it('normalizes phone with plus prefix before logging', function (): void {
    Queue::fake();

    app(SmsService::class)->send('+224621234567', 'Normalized', async: true);

    expect(SmsLog::query()->value('recipient'))->toBe('224621234567');
});

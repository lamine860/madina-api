<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Notification\Enums\SmsStatus;
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

it('delivers pending sms log via job handler', function (): void {
    Http::fake([
        'https://orange-sms.test/oauth/v3/token' => Http::response([
            'access_token' => 'job-token',
            'expires_in' => 3600,
        ], 200),
        'https://orange-sms.test/smsmessaging/v1/outbound/tel%3A%2B224621000001/requests' => Http::response([], 201),
    ]);

    $smsLog = SmsLog::factory()->create([
        'recipient' => '224621234567',
        'message' => 'Job delivery',
        'status' => SmsStatus::Pending,
    ]);

    (new SendSmsJob($smsLog))->handle(app(SmsService::class));

    $smsLog->refresh();

    expect($smsLog->status)->toBe(SmsStatus::Sent)
        ->and($smsLog->sent_at)->not->toBeNull();
});

it('marks sms log failed when job exhausts retries', function (): void {
    $smsLog = SmsLog::factory()->create([
        'recipient' => '224621234567',
        'message' => 'Will fail',
        'status' => SmsStatus::Pending,
    ]);

    $job = new SendSmsJob($smsLog);
    $job->failed(new RuntimeException('Provider down'));

    $smsLog->refresh();

    expect($smsLog->status)->toBe(SmsStatus::Failed)
        ->and($smsLog->error_message)->toBe('Provider down');
});

it('skips delivery when log is already sent', function (): void {
    Http::fake();

    $smsLog = SmsLog::factory()->sent()->create();

    (new SendSmsJob($smsLog))->handle(app(SmsService::class));

    Http::assertNothingSent();
});

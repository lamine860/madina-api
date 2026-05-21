<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Notification\Exceptions\OrangeSmsException;
use Modules\Notification\Services\Providers\OrangeSmsProvider;

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

it('obtains oauth token and sends sms successfully', function (): void {
    Http::fake([
        'https://orange-sms.test/oauth/v3/token' => Http::response([
            'access_token' => 'sms-token-1',
            'expires_in' => 3600,
        ], 200),
        'https://orange-sms.test/smsmessaging/v1/outbound/tel%3A%2B224621000001/requests' => Http::response([
            'outboundSMSMessageRequest' => ['resourceURL' => 'https://orange-sms.test/msg/1'],
        ], 201),
    ]);

    $result = app(OrangeSmsProvider::class)->send('224621234567', 'Bonjour Kilora');

    expect($result)->toBeTrue();
    Http::assertSentCount(2);
});

it('retries send after 401 by refreshing oauth token', function (): void {
    Http::fake([
        'https://orange-sms.test/oauth/v3/token' => Http::sequence()
            ->push(['access_token' => 'expired-token', 'expires_in' => 3600], 200)
            ->push(['access_token' => 'fresh-token', 'expires_in' => 3600], 200),
        'https://orange-sms.test/smsmessaging/v1/outbound/tel%3A%2B224621000001/requests' => Http::sequence()
            ->push(['error' => 'unauthorized'], 401)
            ->push(['outboundSMSMessageRequest' => []], 201),
    ]);

    Cache::put('notification.orange.oauth_token.test', 'expired-token', 3600);

    $result = app(OrangeSmsProvider::class)->send('224621234567', 'Retry test');

    expect($result)->toBeTrue();
    Http::assertSentCount(3);
});

it('rejects invalid guinea phone numbers', function (): void {
    expect(fn () => app(OrangeSmsProvider::class)->send('+221771234567', 'Hi'))
        ->toThrow(OrangeSmsException::class);
});

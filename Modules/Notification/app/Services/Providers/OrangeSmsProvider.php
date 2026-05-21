<?php

declare(strict_types=1);

namespace Modules\Notification\Services\Providers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Notification\Contracts\SmsProviderInterface;
use Modules\Notification\Exceptions\OrangeSmsException;
use Modules\Notification\Services\PhoneNormalizer;
use Throwable;

final class OrangeSmsProvider implements SmsProviderInterface
{
    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer,
    ) {}

    public function send(string $to, string $message): bool
    {
        $recipient = $this->phoneNormalizer->normalize($to);
        $sender = $this->senderNumber();
        $url = $this->sendUrl($sender);

        return $this->postSms($url, $recipient, $sender, $message, retryOnUnauthorized: true);
    }

    public function getBalance(): int
    {
        Log::channel('notifications')->notice('orange.sms.balance_not_supported');

        return 0;
    }

    private function postSms(
        string $url,
        string $recipient,
        string $sender,
        string $message,
        bool $retryOnUnauthorized,
    ): bool {
        try {
            $response = Http::withToken($this->accessToken())
                ->acceptJson()
                ->asJson()
                ->post($url, [
                    'outboundSMSMessageRequest' => [
                        'address' => $this->phoneNormalizer->toTelUri($recipient),
                        'senderAddress' => $this->phoneNormalizer->toTelUri($sender),
                        'outboundSMSTextMessage' => [
                            'message' => $message,
                        ],
                        'senderName' => (string) config('notification.orange.sender_name'),
                    ],
                ]);
        } catch (Throwable $e) {
            Log::channel('notifications')->error('orange.sms.transport', [
                'recipient' => $recipient,
                'message' => $e->getMessage(),
            ]);
            throw new OrangeSmsException('Impossible de joindre Orange SMS.', previous: $e);
        }

        $body = $response->json() ?? [];
        Log::channel('notifications')->info('orange.sms.response', [
            'recipient' => $recipient,
            'status' => $response->status(),
            'body' => $this->sanitizeForLog(is_array($body) ? $body : []),
        ]);

        if ($response->status() === 401 && $retryOnUnauthorized) {
            Cache::forget($this->oauthCacheKey());
            $this->fetchAndCacheAccessToken();

            return $this->postSms($url, $recipient, $sender, $message, retryOnUnauthorized: false);
        }

        if ($response->status() === 429) {
            throw new OrangeSmsException('Orange SMS : limite de débit atteinte.');
        }

        if (! $response->successful()) {
            throw new OrangeSmsException('Orange SMS a refusé l’envoi du message.');
        }

        return true;
    }

    /**
     * @throws OrangeSmsException
     */
    private function accessToken(): string
    {
        $cacheKey = $this->oauthCacheKey();
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->fetchAndCacheAccessToken();
    }

    /**
     * @throws OrangeSmsException
     */
    private function fetchAndCacheAccessToken(): string
    {
        $clientId = (string) config('notification.orange.client_id');
        $clientSecret = (string) config('notification.orange.client_secret');

        if ($clientId === '' || $clientSecret === '') {
            throw new OrangeSmsException('Identifiants Orange SMS non configurés.');
        }

        $url = rtrim((string) config('notification.orange.base_url'), '/')
            .(string) config('notification.orange.oauth_token_path');

        try {
            $response = Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->acceptJson()
                ->post($url, [
                    'grant_type' => 'client_credentials',
                ]);
        } catch (Throwable $e) {
            Log::channel('notifications')->error('orange.sms.oauth.transport', [
                'message' => $e->getMessage(),
            ]);
            throw new OrangeSmsException('Impossible d’obtenir un token Orange SMS.', previous: $e);
        }

        $body = $response->json() ?? [];

        if (! $response->successful()) {
            Log::channel('notifications')->error('orange.sms.oauth.failed', [
                'status' => $response->status(),
                'body' => $this->sanitizeForLog(is_array($body) ? $body : []),
            ]);
            throw new OrangeSmsException('Authentification Orange SMS refusée.');
        }

        $token = $body['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new OrangeSmsException('Réponse OAuth Orange SMS invalide.');
        }

        $expiresIn = isset($body['expires_in']) && is_numeric($body['expires_in'])
            ? (int) $body['expires_in']
            : 3600;
        $ttl = max(60, $expiresIn - 60);

        Cache::put($this->oauthCacheKey(), $token, $ttl);

        return $token;
    }

    /**
     * @throws OrangeSmsException
     */
    private function senderNumber(): string
    {
        $sender = (string) config('notification.orange.sender_number');

        if ($sender === '') {
            throw new OrangeSmsException('Numéro expéditeur Orange SMS non configuré.');
        }

        return $this->phoneNormalizer->normalize($sender);
    }

    private function sendUrl(string $sender): string
    {
        $template = (string) config('notification.orange.sms_send_path_template');

        return rtrim((string) config('notification.orange.base_url'), '/')
            .str_replace('{sender}', $sender, $template);
    }

    private function oauthCacheKey(): string
    {
        return (string) config('notification.orange.oauth_cache_key');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizeForLog(array $payload): array
    {
        $out = $payload;
        foreach (['access_token', 'client_secret', 'authorization'] as $key) {
            if (isset($out[$key])) {
                $out[$key] = '[redacted]';
            }
        }

        return $out;
    }
}

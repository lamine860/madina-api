<?php

declare(strict_types=1);

namespace Modules\Payments\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;
use Modules\Payments\Enums\PaymentMethod;
use Modules\Payments\Enums\PaymentStatus;
use Modules\Payments\Events\PaymentConfirmed;
use Modules\Payments\Exceptions\InvalidOrangeMoneyWebhookSignatureException;
use Modules\Payments\Exceptions\OrangeMoneyException;
use Modules\Payments\Models\Payment;
use Throwable;

final class OrangeMoneyService
{
    /**
     * Crée un paiement Orange en attente, appelle l’API Web Payment et retourne l’URL de redirection.
     *
     * @throws OrangeMoneyException
     */
    public function initiatePayment(Order $order, ?string $customerMsisdn = null): string
    {
        if ($order->status !== OrderStatus::Pending) {
            throw new OrangeMoneyException('Seules les commandes en attente de paiement peuvent être initiées.');
        }

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'transaction_id' => null,
            'amount' => $order->total_amount,
            'currency' => (string) config('payments.orange.currency'),
            'provider' => 'orange',
            'status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::Orange,
            'metadata' => null,
        ]);

        $url = rtrim((string) config('payments.orange.base_url'), '/')
            .(string) config('payments.orange.payment_initiate_path');

        $payload = [
            'merchant_key' => config('payments.orange.merchant_key'),
            'currency' => config('payments.orange.currency'),
            'order_id' => $order->order_number,
            'amount' => (string) $order->total_amount,
            'return_url' => $this->returnUrl(),
            'cancel_url' => $this->cancelUrl(),
            'notif_url' => $this->notifUrl(),
            'reference' => (string) $payment->id,
            'country' => config('payments.orange.country_code'),
        ];

        if ($customerMsisdn !== null && $customerMsisdn !== '') {
            $payload['msisdn'] = $customerMsisdn;
        }

        try {
            $response = Http::withToken($this->accessToken())
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);
        } catch (Throwable $e) {
            Log::channel('payments')->error('orange.initiate.transport', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'message' => $e->getMessage(),
            ]);
            throw new OrangeMoneyException('Impossible de joindre Orange Money.', previous: $e);
        }

        $body = $response->json() ?? [];
        Log::channel('payments')->info('orange.initiate.response', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'status' => $response->status(),
            'body' => $this->sanitizeForLog($body),
        ]);

        if (! $response->successful()) {
            $payment->update([
                'status' => PaymentStatus::Failed,
                'metadata' => array_merge($body, ['http_status' => $response->status()]),
            ]);
            throw new OrangeMoneyException('Orange Money a refusé l’initialisation du paiement.');
        }

        $paymentUrlKey = (string) config('payments.orange.payment_url_key');
        $payTokenKey = (string) config('payments.orange.pay_token_key');
        $txKey = (string) config('payments.orange.transaction_id_key');

        $redirectUrl = $body[$paymentUrlKey] ?? null;
        if (! is_string($redirectUrl) || $redirectUrl === '') {
            $payToken = $body[$payTokenKey] ?? null;
            if (is_string($payToken) && $payToken !== '') {
                $redirectUrl = rtrim((string) config('payments.orange.base_url'), '/')
                    .'/orange-money-webpay/gn/v1/payment?pay_token='.$payToken;
            }
        }

        $transactionId = $body[$txKey] ?? null;

        if (! is_string($redirectUrl) || $redirectUrl === '') {
            $payment->update([
                'status' => PaymentStatus::Failed,
                'metadata' => $body,
            ]);
            throw new OrangeMoneyException('Réponse Orange Money invalide : URL de redirection manquante.');
        }

        $payment->update([
            'transaction_id' => is_string($transactionId) && $transactionId !== '' ? $transactionId : null,
            'metadata' => $body,
        ]);

        return $redirectUrl;
    }

    /**
     * Vérifie la signature du webhook, met à jour le paiement et notifie en cas de succès (idempotent).
     *
     * @throws InvalidOrangeMoneyWebhookSignatureException
     * @throws OrangeMoneyException
     */
    public function verifyWebhook(Request $request): void
    {
        $raw = $request->getContent();
        $headerName = (string) config('payments.orange.webhook_signature_header');
        $provided = $request->header($headerName, '');
        $secret = (string) config('payments.orange.webhook_secret');

        if ($secret === '') {
            throw new InvalidOrangeMoneyWebhookSignatureException('ORANGE_WEBHOOK_SECRET non configuré.');
        }

        if (! is_string($provided) || $provided === '') {
            throw new InvalidOrangeMoneyWebhookSignatureException('Signature webhook manquante.');
        }

        $expected = hash_hmac('sha256', $raw, $secret);

        if (! hash_equals($expected, $provided)) {
            Log::channel('payments')->warning('orange.webhook.bad_signature', [
                'ip' => $request->ip(),
            ]);
            throw new InvalidOrangeMoneyWebhookSignatureException('Signature webhook invalide.');
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new OrangeMoneyException('JSON webhook invalide.');
        }

        if (! is_array($decoded)) {
            throw new OrangeMoneyException('JSON webhook invalide.');
        }

        /** @var array<string, mixed> $data */
        $data = $decoded;

        Log::channel('payments')->info('orange.webhook.payload', [
            'data' => $this->sanitizeForLog($data),
        ]);

        $orderNumber = isset($data['order_number']) && is_string($data['order_number']) ? $data['order_number'] : null;
        $transactionId = isset($data['transaction_id']) && is_string($data['transaction_id']) ? $data['transaction_id'] : null;
        $statusKey = (string) config('payments.orange.status_key');
        $statusRaw = isset($data[$statusKey]) && is_string($data[$statusKey])
            ? strtolower($data[$statusKey])
            : (isset($data['status']) && is_string($data['status']) ? strtolower($data['status']) : '');

        if ($orderNumber === null || $transactionId === null) {
            throw new OrangeMoneyException('Payload webhook incomplet.');
        }

        DB::transaction(function () use ($data, $orderNumber, $transactionId, $statusRaw): void {
            /** @var Payment|null $payment */
            $payment = Payment::query()
                ->where('provider', 'orange')
                ->where('transaction_id', $transactionId)
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                $payment = Payment::query()
                    ->where('provider', 'orange')
                    ->whereHas('order', static fn ($q) => $q->where('order_number', $orderNumber))
                    ->where('status', PaymentStatus::Pending)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();
            }

            if ($payment === null) {
                Log::channel('payments')->warning('orange.webhook.payment_not_found', [
                    'order_number' => $orderNumber,
                    'transaction_id' => $transactionId,
                ]);

                return;
            }

            $previousMeta = is_array($payment->metadata) ? $payment->metadata : [];
            $mergedMeta = array_merge($previousMeta, ['webhook' => $data]);

            if ($payment->status === PaymentStatus::Success) {
                $payment->metadata = $mergedMeta;
                $payment->transaction_id = $transactionId;
                $payment->save();

                return;
            }

            $newStatus = match ($statusRaw) {
                'success', 'completed', 'paid' => PaymentStatus::Success,
                'failed', 'cancelled', 'canceled' => PaymentStatus::Failed,
                default => null,
            };

            if ($newStatus === null) {
                $payment->metadata = $mergedMeta;
                $payment->transaction_id = $transactionId;
                $payment->save();
                Log::channel('payments')->notice('orange.webhook.unknown_status', ['status' => $statusRaw]);

                return;
            }

            $previous = $payment->status;
            $payment->status = $newStatus;
            $payment->transaction_id = $transactionId;
            $payment->metadata = $mergedMeta;
            $payment->save();

            if ($newStatus === PaymentStatus::Success && $previous !== PaymentStatus::Success) {
                $payment->loadMissing('order');
                event(new PaymentConfirmed($payment, $payment->order));
            }
        });
    }

    /**
     * @throws OrangeMoneyException
     */
    private function accessToken(): string
    {
        $cacheKey = (string) config('payments.orange.oauth_cache_key');
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->fetchAndCacheAccessToken();
    }

    /**
     * @throws OrangeMoneyException
     */
    private function fetchAndCacheAccessToken(): string
    {
        $clientId = (string) config('payments.orange.client_id');
        $clientSecret = (string) config('payments.orange.client_secret');

        if ($clientId === '' || $clientSecret === '') {
            throw new OrangeMoneyException('Identifiants Orange Money non configurés.');
        }

        $url = rtrim((string) config('payments.orange.base_url'), '/')
            .(string) config('payments.orange.oauth_token_path');

        try {
            $response = Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->acceptJson()
                ->post($url, [
                    'grant_type' => 'client_credentials',
                ]);
        } catch (Throwable $e) {
            Log::channel('payments')->error('orange.oauth.transport', [
                'message' => $e->getMessage(),
            ]);
            throw new OrangeMoneyException('Impossible d’obtenir un token Orange Money.', previous: $e);
        }

        $body = $response->json() ?? [];

        if (! $response->successful()) {
            Log::channel('payments')->error('orange.oauth.failed', [
                'status' => $response->status(),
                'body' => $this->sanitizeForLog($body),
            ]);
            throw new OrangeMoneyException('Authentification Orange Money refusée.');
        }

        $token = $body['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new OrangeMoneyException('Réponse OAuth Orange Money invalide.');
        }

        $expiresIn = isset($body['expires_in']) && is_numeric($body['expires_in'])
            ? (int) $body['expires_in']
            : 3600;
        $ttl = max(60, $expiresIn - 60);

        Cache::put((string) config('payments.orange.oauth_cache_key'), $token, $ttl);

        return $token;
    }

    private function returnUrl(): string
    {
        $configured = config('payments.orange.return_url');

        return is_string($configured) && $configured !== ''
            ? $configured
            : route('payments.orange.success', [], true);
    }

    private function cancelUrl(): string
    {
        $configured = config('payments.orange.cancel_url');

        return is_string($configured) && $configured !== ''
            ? $configured
            : route('payments.orange.cancel', [], true);
    }

    private function notifUrl(): string
    {
        $configured = config('payments.orange.notif_url');

        return is_string($configured) && $configured !== ''
            ? $configured
            : route('api.payments.orange.webhook', [], true);
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

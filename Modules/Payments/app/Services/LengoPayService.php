<?php

declare(strict_types=1);

namespace Modules\Payments\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;
use Modules\Payments\Enums\PaymentMethod;
use Modules\Payments\Enums\PaymentStatus;
use Modules\Payments\Events\PaymentConfirmed;
use Modules\Payments\Exceptions\InvalidLengoPayWebhookSignatureException;
use Modules\Payments\Exceptions\LengoPayException;
use Modules\Payments\Models\Payment;
use Throwable;

final class LengoPayService
{
    /**
     * Crée un paiement en attente, appelle LengoPay et retourne l’URL de redirection.
     *
     * @throws LengoPayException
     */
    public function initiatePayment(Order $order, PaymentMethod $paymentMethod): string
    {
        if ($order->status !== OrderStatus::Pending) {
            throw new LengoPayException('Seules les commandes en attente de paiement peuvent être initiées.');
        }

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'transaction_id' => null,
            'amount' => $order->total_amount,
            'currency' => 'GNF',
            'provider' => 'lengopay',
            'status' => PaymentStatus::Pending,
            'payment_method' => $paymentMethod,
            'metadata' => null,
        ]);

        $url = rtrim((string) config('payments.lengopay.base_url'), '/').(string) config('payments.lengopay.initiate_path');
        $payload = [
            'merchant_id' => config('payments.lengopay.merchant_id'),
            'order_number' => $order->order_number,
            'amount' => (string) $order->total_amount,
            'currency' => 'GNF',
            'payment_method' => $paymentMethod->value,
            'success_url' => route('payments.lengopay.success', [], true),
            'cancel_url' => route('payments.lengopay.cancel', [], true),
            'webhook_url' => route('api.payments.lengopay.webhook', [], true),
            'internal_payment_id' => $payment->id,
        ];

        try {
            $response = Http::withHeaders($this->defaultHeaders())
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);
        } catch (Throwable $e) {
            Log::channel('payments')->error('lengopay.initiate.transport', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'message' => $e->getMessage(),
            ]);
            throw new LengoPayException('Impossible de joindre LengoPay.', previous: $e);
        }

        $body = $response->json() ?? [];
        Log::channel('payments')->info('lengopay.initiate.response', [
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
            throw new LengoPayException('LengoPay a refusé l’initialisation du paiement.');
        }

        $redirectKey = (string) config('payments.lengopay.redirect_url_key');
        $txKey = (string) config('payments.lengopay.transaction_id_key');
        $redirectUrl = $body[$redirectKey] ?? null;
        $transactionId = $body[$txKey] ?? null;

        if (! is_string($redirectUrl) || $redirectUrl === '') {
            $payment->update([
                'status' => PaymentStatus::Failed,
                'metadata' => $body,
            ]);
            throw new LengoPayException('Réponse LengoPay invalide : URL de redirection manquante.');
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
     * @throws InvalidLengoPayWebhookSignatureException
     * @throws LengoPayException
     */
    public function verifyWebhook(Request $request): void
    {
        $raw = $request->getContent();
        $headerName = (string) config('payments.lengopay.webhook_signature_header');
        $provided = $request->header($headerName, '');
        $secret = (string) config('payments.lengopay.webhook_secret');

        if ($secret === '') {
            throw new InvalidLengoPayWebhookSignatureException('LENGOPAY_WEBHOOK_SECRET non configuré.');
        }

        if (! is_string($provided) || $provided === '') {
            throw new InvalidLengoPayWebhookSignatureException('Signature webhook manquante.');
        }

        $expected = hash_hmac('sha256', $raw, $secret);

        if (! hash_equals($expected, $provided)) {
            Log::channel('payments')->warning('lengopay.webhook.bad_signature', [
                'ip' => $request->ip(),
            ]);
            throw new InvalidLengoPayWebhookSignatureException('Signature webhook invalide.');
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new LengoPayException('JSON webhook invalide.');
        }

        if (! is_array($decoded)) {
            throw new LengoPayException('JSON webhook invalide.');
        }

        /** @var array<string, mixed> $data */
        $data = $decoded;

        Log::channel('payments')->info('lengopay.webhook.payload', [
            'data' => $this->sanitizeForLog($data),
        ]);

        $orderNumber = isset($data['order_number']) && is_string($data['order_number']) ? $data['order_number'] : null;
        $transactionId = isset($data['transaction_id']) && is_string($data['transaction_id']) ? $data['transaction_id'] : null;
        $statusRaw = isset($data['status']) && is_string($data['status']) ? strtolower($data['status']) : '';

        if ($orderNumber === null || $transactionId === null) {
            throw new LengoPayException('Payload webhook incomplet.');
        }

        DB::transaction(function () use ($data, $orderNumber, $transactionId, $statusRaw): void {
            /** @var Payment|null $payment */
            $payment = Payment::query()
                ->where('transaction_id', $transactionId)
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                $payment = Payment::query()
                    ->whereHas('order', static fn ($q) => $q->where('order_number', $orderNumber))
                    ->where('status', PaymentStatus::Pending)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();
            }

            if ($payment === null) {
                Log::channel('payments')->warning('lengopay.webhook.payment_not_found', [
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
                Log::channel('payments')->notice('lengopay.webhook.unknown_status', ['status' => $statusRaw]);

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
     * @return array<string, string>
     */
    private function defaultHeaders(): array
    {
        $key = (string) config('payments.lengopay.api_key');

        return [
            'Authorization' => 'Bearer '.$key,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizeForLog(array $payload): array
    {
        $out = $payload;
        if (isset($out['authorization'])) {
            $out['authorization'] = '[redacted]';
        }

        return $out;
    }
}

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Core\Enums\UserRole;
use Modules\Core\Models\User;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;
use Modules\Payments\Enums\PaymentMethod;
use Modules\Payments\Enums\PaymentStatus;
use Modules\Payments\Models\Payment;

beforeEach(function (): void {
    Cache::flush();

    config([
        'payments.orange.base_url' => 'https://orange.test',
        'payments.orange.oauth_token_path' => '/oauth/v3/token',
        'payments.orange.payment_initiate_path' => '/pay',
        'payments.orange.client_id' => 'orange-client',
        'payments.orange.client_secret' => 'orange-secret',
        'payments.orange.merchant_key' => 'merchant-orange',
        'payments.orange.webhook_secret' => 'orange-wh-secret',
        'payments.orange.webhook_signature_header' => 'X-Orange-Signature',
        'payments.orange.payment_url_key' => 'payment_url',
        'payments.orange.transaction_id_key' => 'txnid',
        'payments.orange.currency' => 'GNF',
        'payments.orange.country_code' => 'GN',
    ]);
});

it('initiates orange payment and returns redirect url', function (): void {
    Http::fake([
        'https://orange.test/oauth/v3/token' => Http::response([
            'access_token' => 'orange-token-1',
            'expires_in' => 3600,
        ], 200),
        'https://orange.test/pay' => Http::response([
            'payment_url' => 'https://checkout.orange.test/pay',
            'txnid' => 'om-tx-1',
        ], 200),
    ]);

    $user = User::factory()->create(['role' => UserRole::Customer]);
    $order = Order::query()->create([
        'order_number' => 'ORD-'.uniqid(),
        'user_id' => $user->id,
        'total_amount' => '99.50',
        'status' => OrderStatus::Pending,
        'shipping_address' => ['line1' => '1 rue', 'city' => 'C', 'postal_code' => '75001', 'country' => 'FR'],
        'billing_address' => null,
        'notes' => null,
    ]);

    $this->actingAs($user, 'sanctum');
    $response = $this->postJson("/api/v1/orders/{$order->id}/payments/orange/initiate", [
        'customer_msisdn' => '224621234567',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('redirect_url', 'https://checkout.orange.test/pay');

    $payment = Payment::query()->where('order_id', $order->id)->first();
    expect(Payment::query()->where('order_id', $order->id)->count())->toBe(1)
        ->and($payment)->not->toBeNull()
        ->and($payment->provider)->toBe('orange')
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->payment_method)->toBe(PaymentMethod::Orange)
        ->and($payment->transaction_id)->toBe('om-tx-1');
});

it('rejects orange webhook with invalid signature', function (): void {
    $this->postJson('/api/v1/payments/orange/webhook', [
        'order_number' => 'x',
        'transaction_id' => 'y',
        'status' => 'success',
    ], [
        'X-Orange-Signature' => 'bad',
    ])->assertUnauthorized();
});

it('confirms payment via orange webhook and marks order paid once when webhook is replayed', function (): void {
    $user = User::factory()->create(['role' => UserRole::Customer]);
    $order = Order::query()->create([
        'order_number' => 'ORD-OM-'.uniqid(),
        'user_id' => $user->id,
        'total_amount' => '10.00',
        'status' => OrderStatus::Pending,
        'shipping_address' => ['line1' => '1 rue', 'city' => 'C', 'postal_code' => '75001', 'country' => 'FR'],
        'billing_address' => null,
        'notes' => null,
    ]);

    Payment::query()->create([
        'order_id' => $order->id,
        'transaction_id' => 'om-tx-replay',
        'amount' => $order->total_amount,
        'currency' => 'GNF',
        'provider' => 'orange',
        'status' => PaymentStatus::Pending,
        'payment_method' => PaymentMethod::Orange,
        'metadata' => null,
    ]);

    $payload = [
        'order_number' => $order->order_number,
        'transaction_id' => 'om-tx-replay',
        'status' => 'success',
    ];
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $sig = hash_hmac('sha256', $raw, 'orange-wh-secret');

    $this->postJson('/api/v1/payments/orange/webhook', $payload, [
        'X-Orange-Signature' => $sig,
    ])->assertSuccessful();

    $this->postJson('/api/v1/payments/orange/webhook', $payload, [
        'X-Orange-Signature' => $sig,
    ])->assertSuccessful();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Paid);

    $payment = Payment::query()->where('transaction_id', 'om-tx-replay')->first();
    expect($payment)->not->toBeNull()
        ->and($payment->status)->toBe(PaymentStatus::Success)
        ->and(is_array($payment->metadata))->toBeTrue()
        ->and(isset($payment->metadata['webhook']))->toBeTrue();
});

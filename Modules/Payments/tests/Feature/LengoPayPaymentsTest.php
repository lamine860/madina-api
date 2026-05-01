<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\Core\Enums\UserRole;
use Modules\Core\Models\User;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;
use Modules\Payments\Enums\PaymentMethod;
use Modules\Payments\Enums\PaymentStatus;
use Modules\Payments\Models\Payment;

beforeEach(function (): void {
    config([
        'payments.lengopay.base_url' => 'https://lengo.test',
        'payments.lengopay.initiate_path' => '/pay',
        'payments.lengopay.api_key' => 'test-key',
        'payments.lengopay.merchant_id' => 'merchant-1',
        'payments.lengopay.webhook_secret' => 'wh-secret',
        'payments.lengopay.webhook_signature_header' => 'X-Lengopay-Signature',
        'payments.lengopay.redirect_url_key' => 'redirect_url',
        'payments.lengopay.transaction_id_key' => 'transaction_id',
    ]);
});

it('initiates lengopay payment and returns redirect url', function (): void {
    Http::fake([
        'https://lengo.test/pay' => Http::response([
            'redirect_url' => 'https://checkout.test/pay',
            'transaction_id' => 'lp-tx-1',
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
    $response = $this->postJson("/api/v1/orders/{$order->id}/payments/lengopay/initiate", [
        'payment_method' => PaymentMethod::Orange->value,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('redirect_url', 'https://checkout.test/pay');

    expect(Payment::query()->where('order_id', $order->id)->count())->toBe(1)
        ->and(Payment::query()->first()->status)->toBe(PaymentStatus::Pending)
        ->and(Payment::query()->first()->transaction_id)->toBe('lp-tx-1');
});

it('rejects webhook with invalid signature', function (): void {
    $this->postJson('/api/v1/payments/lengopay/webhook', [
        'order_number' => 'x',
        'transaction_id' => 'y',
        'status' => 'success',
    ], [
        'X-Lengopay-Signature' => 'bad',
    ])->assertUnauthorized();
});

it('confirms payment via webhook and marks order paid once when webhook is replayed', function (): void {
    $user = User::factory()->create(['role' => UserRole::Customer]);
    $order = Order::query()->create([
        'order_number' => 'ORD-WH-'.uniqid(),
        'user_id' => $user->id,
        'total_amount' => '10.00',
        'status' => OrderStatus::Pending,
        'shipping_address' => ['line1' => '1 rue', 'city' => 'C', 'postal_code' => '75001', 'country' => 'FR'],
        'billing_address' => null,
        'notes' => null,
    ]);

    Payment::query()->create([
        'order_id' => $order->id,
        'transaction_id' => 'lp-tx-replay',
        'amount' => $order->total_amount,
        'currency' => 'GNF',
        'provider' => 'lengopay',
        'status' => PaymentStatus::Pending,
        'payment_method' => PaymentMethod::Moov,
        'metadata' => null,
    ]);

    $payload = [
        'order_number' => $order->order_number,
        'transaction_id' => 'lp-tx-replay',
        'status' => 'success',
    ];
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $sig = hash_hmac('sha256', $raw, 'wh-secret');

    $this->postJson('/api/v1/payments/lengopay/webhook', $payload, [
        'X-Lengopay-Signature' => $sig,
    ])->assertSuccessful();

    $this->postJson('/api/v1/payments/lengopay/webhook', $payload, [
        'X-Lengopay-Signature' => $sig,
    ])->assertSuccessful();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Paid);

    $payment = Payment::query()->where('transaction_id', 'lp-tx-replay')->first();
    expect($payment)->not->toBeNull()
        ->and($payment->status)->toBe(PaymentStatus::Success)
        ->and(is_array($payment->metadata))->toBeTrue()
        ->and(isset($payment->metadata['webhook']))->toBeTrue();
});

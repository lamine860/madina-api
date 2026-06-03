<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Modules\Core\Database\Factories\CustomerFactory;
use Modules\Core\Enums\UserRole;
use Modules\Core\Models\User;
use Modules\Notification\Jobs\SendSmsJob;
use Modules\Notification\Models\SmsLog;
use Modules\Notification\Notifications\OrderSmsMessage;
use Modules\Orders\Events\OrderPaid;
use Modules\Shipping\Enums\DeliveryMode;
use Modules\Shipping\Enums\ShipmentStatus;
use Modules\Shipping\Models\DeliveryProvider;
use Modules\Shipping\Models\Shipment;
use Modules\Shipping\Models\ShippingRate;

beforeEach(function (): void {
    Queue::fake();
});

it('queues order paid sms when OrderPaid is dispatched', function (): void {
    $buyer = User::factory()->create(['role' => UserRole::Customer]);
    CustomerFactory::new()->create([
        'user_id' => $buyer->id,
        'phone' => '224621234567',
    ]);

    $setup = createSellerShopVariant();
    $order = createPaidOrderFromSetups([
        ['shop' => $setup['shop'], 'variant' => $setup['variant'], 'subtotal' => '10.00'],
    ]);
    $order->update(['user_id' => $buyer->id]);

    OrderPaid::dispatch($order->fresh());

    $messages = SmsLog::query()
        ->where('recipient', '224621234567')
        ->pluck('message')
        ->all();

    expect($messages)->toContain(OrderSmsMessage::orderPaid($order->order_number));

    Queue::assertPushedOn('notifications', SendSmsJob::class);
});

it('queues shipment confirmation sms when a shipment is created', function (): void {
    $buyer = User::factory()->create(['role' => UserRole::Customer]);
    CustomerFactory::new()->create([
        'user_id' => $buyer->id,
        'phone' => '224621999888',
    ]);

    $setup = createSellerShopVariant();
    $order = createPaidOrderFromSetups([
        ['shop' => $setup['shop'], 'variant' => $setup['variant'], 'subtotal' => '10.00'],
    ]);
    $order->update(['user_id' => $buyer->id]);

    $kilora = DeliveryProvider::query()->where('name', 'Kilora Internal')->firstOrFail();
    $flash = ShippingRate::query()->where('code', 'FLASH')->firstOrFail();

    Shipment::query()->create([
        'order_id' => $order->id,
        'shop_id' => $setup['shop']->id,
        'provider_id' => $kilora->id,
        'service_id' => $flash->id,
        'exit_code' => 'EXIT01',
        'confirmation_code' => 'CONF01',
        'status' => ShipmentStatus::Pending,
        'delivery_mode' => DeliveryMode::KiloraDelivery,
    ]);

    $logs = SmsLog::query()->where('recipient', '224621999888')->get();

    expect($logs)->toHaveCount(1)
        ->and($logs->first()->message)->toBe(OrderSmsMessage::shipmentReady('CONF01'));
});

it('skips sms when the buyer has no phone on file', function (): void {
    $setup = createSellerShopVariant();
    $order = createPaidOrderFromSetups([
        ['shop' => $setup['shop'], 'variant' => $setup['variant'], 'subtotal' => '10.00'],
    ]);

    OrderPaid::dispatch($order);

    expect(SmsLog::query()->count())->toBe(0);

    Queue::assertNothingPushed();
});

it('sends order paid and per-shipment sms through the full OrderPaid flow', function (): void {
    $buyer = User::factory()->create(['role' => UserRole::Customer]);
    CustomerFactory::new()->create([
        'user_id' => $buyer->id,
        'phone' => '224621111222',
    ]);

    $a = createSellerShopVariant('10.00');
    $b = createSellerShopVariant('20.00');
    $order = createPaidOrderFromSetups([
        ['shop' => $a['shop'], 'variant' => $a['variant'], 'subtotal' => '10.00'],
        ['shop' => $b['shop'], 'variant' => $b['variant'], 'subtotal' => '20.00'],
    ]);
    $order->update(['user_id' => $buyer->id]);

    OrderPaid::dispatch($order->fresh());

    $messages = SmsLog::query()
        ->where('recipient', '224621111222')
        ->orderBy('id')
        ->pluck('message')
        ->all();

    expect($messages)->toHaveCount(3)
        ->and($messages[0])->toBe(OrderSmsMessage::orderPaid($order->order_number));

    $shipments = Shipment::query()->where('order_id', $order->id)->get();

    foreach ($shipments as $shipment) {
        expect($messages)->toContain(OrderSmsMessage::shipmentReady((string) $shipment->confirmation_code));
    }
});

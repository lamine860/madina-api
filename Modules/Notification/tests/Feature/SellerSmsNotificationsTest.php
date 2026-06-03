<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Modules\Core\Database\Factories\CustomerFactory;
use Modules\Core\Enums\UserRole;
use Modules\Core\Models\User;
use Modules\Notification\Jobs\SendSmsJob;
use Modules\Notification\Models\SmsLog;
use Modules\Notification\Notifications\SellerSmsMessage;
use Modules\Orders\Events\OrderPaid;
use Modules\Shipping\Enums\DeliveryMode;
use Modules\Shipping\Enums\ShipmentStatus;
use Modules\Shipping\Models\DeliveryProvider;
use Modules\Shipping\Models\Shipment;
use Modules\Shipping\Models\ShippingRate;

beforeEach(function (): void {
    Queue::fake();
});

it('queues new order sms to each seller when OrderPaid is dispatched', function (): void {
    $a = createSellerShopVariant('10.00');
    $b = createSellerShopVariant('20.00');

    CustomerFactory::new()->create([
        'user_id' => $a['seller']->id,
        'phone' => '224621100001',
    ]);
    CustomerFactory::new()->create([
        'user_id' => $b['seller']->id,
        'phone' => '224621100002',
    ]);

    $order = createPaidOrderFromSetups([
        ['shop' => $a['shop'], 'variant' => $a['variant'], 'subtotal' => '10.00'],
        ['shop' => $b['shop'], 'variant' => $b['variant'], 'subtotal' => '20.00'],
    ]);

    OrderPaid::dispatch($order);

    $sellerA = SmsLog::query()->where('recipient', '224621100001')->pluck('message')->all();
    $sellerB = SmsLog::query()->where('recipient', '224621100002')->pluck('message')->all();

    expect($sellerA)->toContain(SellerSmsMessage::newPaidOrder($order->order_number))
        ->and($sellerB)->toContain(SellerSmsMessage::newPaidOrder($order->order_number));

    Queue::assertPushed(SendSmsJob::class);
});

it('queues exit code sms to the seller when a shipment is created', function (): void {
    $setup = createSellerShopVariant();

    CustomerFactory::new()->create([
        'user_id' => $setup['seller']->id,
        'phone' => '224621200001',
    ]);

    $buyer = User::factory()->create(['role' => UserRole::Customer]);
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
        'exit_code' => 'EXIT99',
        'confirmation_code' => 'CONF99',
        'status' => ShipmentStatus::Pending,
        'delivery_mode' => DeliveryMode::KiloraDelivery,
    ]);

    $message = SmsLog::query()
        ->where('recipient', '224621200001')
        ->value('message');

    expect($message)->toBe(SellerSmsMessage::shipmentExitCode($order->order_number, 'EXIT99'));
});

it('skips seller sms when the shop owner has no phone on file', function (): void {
    $setup = createSellerShopVariant();
    $order = createPaidOrderFromSetups([
        ['shop' => $setup['shop'], 'variant' => $setup['variant'], 'subtotal' => '10.00'],
    ]);

    OrderPaid::dispatch($order);

    expect(SmsLog::query()->count())->toBe(0);
});

it('notifies each seller with exit code through the full OrderPaid flow', function (): void {
    $a = createSellerShopVariant('10.00');
    $b = createSellerShopVariant('20.00');

    CustomerFactory::new()->create([
        'user_id' => $a['seller']->id,
        'phone' => '224621300001',
    ]);
    CustomerFactory::new()->create([
        'user_id' => $b['seller']->id,
        'phone' => '224621300002',
    ]);

    $order = createPaidOrderFromSetups([
        ['shop' => $a['shop'], 'variant' => $a['variant'], 'subtotal' => '10.00'],
        ['shop' => $b['shop'], 'variant' => $b['variant'], 'subtotal' => '20.00'],
    ]);

    OrderPaid::dispatch($order->fresh());

    $shipments = Shipment::query()->where('order_id', $order->id)->get();

    foreach ($shipments as $shipment) {
        $shipment->load('shop.user');
        $phone = $shipment->shop_id === $a['shop']->id ? '224621300001' : '224621300002';

        expect(
            SmsLog::query()
                ->where('recipient', $phone)
                ->pluck('message')
                ->all()
        )->toContain(SellerSmsMessage::shipmentExitCode($order->order_number, (string) $shipment->exit_code));
    }
});

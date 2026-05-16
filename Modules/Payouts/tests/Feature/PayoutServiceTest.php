<?php

declare(strict_types=1);

use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Events\OrderPaid;
use Modules\Payouts\Enums\PayoutStatus;
use Modules\Payouts\Models\Payout;
use Modules\Payouts\Services\PayoutService;
use Modules\Shipping\Enums\DeliveryMode;
use Modules\Shipping\Enums\DeliveryProviderType;
use Modules\Shipping\Enums\ShipmentStatus;
use Modules\Shipping\Models\DeliveryProvider;
use Modules\Shipping\Models\Shipment;
use Modules\Shipping\Models\ShippingRate;

beforeEach(function (): void {
    config(['payouts.commission_rate' => '0.10']);
});

it('creates a pending payout with commission and idempotency key', function (): void {
    $setup = createSellerShopVariant();
    $order = createPaidOrderFromSetups([
        ['shop' => $setup['shop'], 'variant' => $setup['variant'], 'subtotal' => '100.00'],
    ]);
    $service = app(PayoutService::class);

    $payout = $service->createPendingForShopIfMissing($order, (int) $setup['shop']->id);
    $again = $service->createPendingForShopIfMissing($order, (int) $setup['shop']->id);

    expect(Payout::query()->where('order_id', $order->id)->where('shop_id', $setup['shop']->id)->count())->toBe(1)
        ->and($again->id)->toBe($payout->id)
        ->and((string) $payout->commission)->toBe('10.00')
        ->and((string) $payout->amount)->toBe('90.00')
        ->and($payout->currency)->toBe('GNF')
        ->and($payout->idempotency_key)->toBe("order-{$order->id}-shop-{$setup['shop']->id}")
        ->and($payout->status)->toBe(PayoutStatus::Pending);
});

it('marks payout ready from pending and is idempotent', function (): void {
    $setup = createSellerShopVariant();
    $order = createPaidOrderFromSetups([
        ['shop' => $setup['shop'], 'variant' => $setup['variant'], 'subtotal' => '50.00'],
    ]);
    $service = app(PayoutService::class);
    $service->createPendingForShopIfMissing($order, (int) $setup['shop']->id);

    $service->markReady($order, (int) $setup['shop']->id);
    $service->markReady($order->fresh(), (int) $setup['shop']->id);

    $payout = Payout::query()->where('order_id', $order->id)->sole();
    expect($payout->status)->toBe(PayoutStatus::Ready);
});

it('no-ops markReady when payout is missing or not pending', function (): void {
    $setup = createSellerShopVariant();
    $order = createPaidOrderFromSetups([
        ['shop' => $setup['shop'], 'variant' => $setup['variant'], 'subtotal' => '25.00'],
    ]);
    $service = app(PayoutService::class);

    $service->markReady($order, (int) $setup['shop']->id);
    expect(Payout::query()->where('order_id', $order->id)->count())->toBe(0);

    $payout = $service->createPendingForShopIfMissing($order, (int) $setup['shop']->id);
    $payout->update(['status' => PayoutStatus::Ready]);
    $service->markReady($order->fresh(), (int) $setup['shop']->id);
    expect($payout->fresh()->status)->toBe(PayoutStatus::Ready);

    $payout->update(['status' => PayoutStatus::Paid]);
    $service->markReady($order->fresh(), (int) $setup['shop']->id);
    expect($payout->fresh()->status)->toBe(PayoutStatus::Paid);
});

it('does not mark payout ready when order status disallows release', function (): void {
    $setup = createSellerShopVariant();
    $service = app(PayoutService::class);

    foreach ([OrderStatus::Pending, OrderStatus::Cancelled, OrderStatus::Refunded] as $status) {
        $order = createOrderWithItems([
            [
                'shop' => $setup['shop'],
                'variant' => $setup['variant'],
                'unit_price' => '10.00',
                'subtotal' => '10.00',
            ],
        ], $status);
        $service->createPendingForShopIfMissing($order, (int) $setup['shop']->id);
        $service->markReady($order, (int) $setup['shop']->id);

        expect(Payout::query()->where('order_id', $order->id)->value('status'))->toBe(PayoutStatus::Pending);
    }
});

it('marks payout ready for paid processing and shipped orders', function (): void {
    $setup = createSellerShopVariant();
    $service = app(PayoutService::class);

    foreach ([OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped] as $status) {
        $order = createOrderWithItems([
            [
                'shop' => $setup['shop'],
                'variant' => $setup['variant'],
                'unit_price' => '10.00',
                'subtotal' => '10.00',
            ],
        ], $status);
        $service->createPendingForShopIfMissing($order, (int) $setup['shop']->id);
        $service->markReady($order, (int) $setup['shop']->id);

        expect(Payout::query()->where('order_id', $order->id)->value('status'))->toBe(PayoutStatus::Ready);
    }
});

it('creates distinct payouts per shop with correct subtotals', function (): void {
    $a = createSellerShopVariant();
    $b = createSellerShopVariant();
    $order = createPaidOrderFromSetups([
        ['shop' => $a['shop'], 'variant' => $a['variant'], 'subtotal' => '100.00'],
        ['shop' => $b['shop'], 'variant' => $b['variant'], 'subtotal' => '200.00'],
    ]);
    $service = app(PayoutService::class);

    $service->createPendingForShopIfMissing($order, (int) $a['shop']->id);
    $service->createPendingForShopIfMissing($order, (int) $b['shop']->id);

    $payoutA = Payout::query()->where('order_id', $order->id)->where('shop_id', $a['shop']->id)->sole();
    $payoutB = Payout::query()->where('order_id', $order->id)->where('shop_id', $b['shop']->id)->sole();

    expect((string) $payoutA->commission)->toBe('10.00')
        ->and((string) $payoutA->amount)->toBe('90.00')
        ->and((string) $payoutB->commission)->toBe('20.00')
        ->and((string) $payoutB->amount)->toBe('180.00');
});

it('releases shop self-delivery payout only after delivery verification', function (): void {
    $setup = createSellerShopVariant('50.00');
    $order = createPaidOrderFromSetups([
        ['shop' => $setup['shop'], 'variant' => $setup['variant'], 'subtotal' => '50.00'],
    ]);

    $shopProvider = DeliveryProvider::query()->where('type', DeliveryProviderType::Shop)->sole();
    $service = ShippingRate::query()->where('code', 'ECO')->sole();

    app(PayoutService::class)->createPendingForShopIfMissing($order, (int) $setup['shop']->id);

    $shipment = Shipment::query()->create([
        'order_id' => $order->id,
        'shop_id' => $setup['shop']->id,
        'provider_id' => $shopProvider->id,
        'service_id' => $service->id,
        'exit_code' => null,
        'confirmation_code' => 'ABCDEF',
        'status' => ShipmentStatus::Pending,
        'delivery_mode' => DeliveryMode::ShopSelfDelivery,
    ]);

    $payout = Payout::query()->where('order_id', $order->id)->sole();
    expect($payout->status)->toBe(PayoutStatus::Pending);

    $this->actingAs($setup['seller'], 'sanctum');
    $this->postJson(SHIPMENTS_VERIFY_DELIVERY_URL, [
        'shipment_id' => $shipment->id,
        'confirmation_code' => 'ABCDEF',
    ])->assertSuccessful();

    expect($payout->fresh()->status)->toBe(PayoutStatus::Ready);
});

it('releases Kilora payout on pickup via OrderPaid fulfillment flow', function (): void {
    $setup = createSellerShopVariant('80.00');
    $order = createPaidOrderFromSetups([
        ['shop' => $setup['shop'], 'variant' => $setup['variant'], 'subtotal' => '80.00'],
    ]);
    OrderPaid::dispatch($order);

    $payout = Payout::query()->where('order_id', $order->id)->sole();
    expect($payout->status)->toBe(PayoutStatus::Pending);

    $shipment = Shipment::query()->where('order_id', $order->id)->sole();
    $this->actingAs($setup['seller'], 'sanctum');
    $this->postJson(SHIPMENTS_VERIFY_PICKUP_URL, [
        'shipment_id' => $shipment->id,
        'exit_code' => (string) $shipment->exit_code,
    ])->assertSuccessful();

    expect($payout->fresh()->status)->toBe(PayoutStatus::Ready);
});

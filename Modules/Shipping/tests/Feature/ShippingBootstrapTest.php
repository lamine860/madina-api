<?php

declare(strict_types=1);

use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Events\OrderPaid;
use Modules\Payouts\Models\Payout;
use Modules\Shipping\Models\Shipment;
use Modules\Shipping\Models\ShippingRate;
use Modules\Shipping\Services\ShippingService;

it('is idempotent when OrderPaid is dispatched multiple times', function (): void {
    $a = createSellerShopVariant('10.00');
    $b = createSellerShopVariant('20.00');
    $order = createPaidOrderFromSetups([
        ['shop' => $a['shop'], 'variant' => $a['variant'], 'subtotal' => '10.00'],
        ['shop' => $b['shop'], 'variant' => $b['variant'], 'subtotal' => '20.00'],
    ]);

    OrderPaid::dispatch($order);
    OrderPaid::dispatch($order->fresh());

    expect(Shipment::query()->where('order_id', $order->id)->count())->toBe(2)
        ->and(Payout::query()->where('order_id', $order->id)->count())->toBe(2);
});

it('does not create shipments when bootstrapping a non-paid order', function (): void {
    $setup = createSellerShopVariant();
    $order = createOrderWithItems([
        [
            'shop' => $setup['shop'],
            'variant' => $setup['variant'],
            'unit_price' => '10.00',
            'subtotal' => '10.00',
        ],
    ], OrderStatus::Pending);

    app(ShippingService::class)->bootstrapFulfillmentForPaidOrder($order);

    expect(Shipment::query()->where('order_id', $order->id)->count())->toBe(0);
});

it('assigns the default FLASH service to bootstrapped shipments', function (): void {
    $setup = createSellerShopVariant();
    $order = createPaidOrderFromSetups([
        ['shop' => $setup['shop'], 'variant' => $setup['variant'], 'subtotal' => '10.00'],
    ]);

    OrderPaid::dispatch($order);

    $flashServiceId = ShippingRate::query()->where('code', config('shipping.default_service_code', 'FLASH'))->value('id');
    $shipment = Shipment::query()->where('order_id', $order->id)->sole();

    expect((int) $shipment->service_id)->toBe((int) $flashServiceId);
});

it('generates unique exit and confirmation codes per shipment', function (): void {
    $a = createSellerShopVariant();
    $b = createSellerShopVariant();
    $order = createPaidOrderFromSetups([
        ['shop' => $a['shop'], 'variant' => $a['variant'], 'subtotal' => '10.00'],
        ['shop' => $b['shop'], 'variant' => $b['variant'], 'subtotal' => '20.00'],
    ]);

    OrderPaid::dispatch($order);

    $shipments = Shipment::query()->where('order_id', $order->id)->get();
    $exitCodes = $shipments->pluck('exit_code')->filter()->all();
    $confirmationCodes = $shipments->pluck('confirmation_code')->all();

    expect($exitCodes)->toHaveCount(2)
        ->and(count(array_unique($exitCodes)))->toBe(2)
        ->and(count(array_unique($confirmationCodes)))->toBe(2);
});

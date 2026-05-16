<?php

declare(strict_types=1);

use Modules\Core\Enums\UserRole;
use Modules\Core\Models\User;
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

it('requires exit code for Kilora pickup verification', function (): void {
    $setup = createSellerShopVariant('10.00');
    $order = createPaidOrderFromSetups([
        ['shop' => $setup['shop'], 'variant' => $setup['variant'], 'subtotal' => '10.00'],
    ]);
    OrderPaid::dispatch($order);

    $shipment = Shipment::query()->where('order_id', $order->id)->sole();
    $this->actingAs($setup['seller'], 'sanctum');

    $this->postJson(SHIPMENTS_VERIFY_PICKUP_URL, [
        'shipment_id' => $shipment->id,
        'exit_code' => null,
    ])->assertUnprocessable()
        ->assertJsonPath('errors.exit_code.0', 'Le code sortie est obligatoire pour la livraison Kilora.');
});

it('rejects invalid exit code on pickup verification', function (): void {
    $setup = createSellerShopVariant('10.00');
    $order = createPaidOrderFromSetups([
        ['shop' => $setup['shop'], 'variant' => $setup['variant'], 'subtotal' => '10.00'],
    ]);
    OrderPaid::dispatch($order);

    $shipment = Shipment::query()->where('order_id', $order->id)->sole();
    $this->actingAs($setup['seller'], 'sanctum');

    $this->postJson(SHIPMENTS_VERIFY_PICKUP_URL, [
        'shipment_id' => $shipment->id,
        'exit_code' => 'WRONG1',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.exit_code.0', 'Code sortie invalide.');
});

it('allows idempotent Kilora pickup verification with the same exit code', function (): void {
    $setup = createSellerShopVariant('10.00');
    $order = createPaidOrderFromSetups([
        ['shop' => $setup['shop'], 'variant' => $setup['variant'], 'subtotal' => '10.00'],
    ]);
    OrderPaid::dispatch($order);

    $shipment = Shipment::query()->where('order_id', $order->id)->sole();
    $exit = (string) $shipment->exit_code;
    $this->actingAs($setup['seller'], 'sanctum');

    $this->postJson(SHIPMENTS_VERIFY_PICKUP_URL, [
        'shipment_id' => $shipment->id,
        'exit_code' => $exit,
    ])->assertSuccessful();

    $this->postJson(SHIPMENTS_VERIFY_PICKUP_URL, [
        'shipment_id' => $shipment->id,
        'exit_code' => $exit,
    ])->assertSuccessful();

    expect($shipment->fresh()->status)->toBe(ShipmentStatus::PickedUp);
});

it('rejects pickup verification for shop self-delivery shipments', function (): void {
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

    $this->actingAs($setup['seller'], 'sanctum');

    $this->postJson(SHIPMENTS_VERIFY_PICKUP_URL, [
        'shipment_id' => $shipment->id,
        'exit_code' => 'IGNORE',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.exit_code.0', 'Le retrait avec code sortie ne s’applique pas à l’auto-livraison boutique.');
});

it('forbids pickup verification for unrelated seller and customer', function (): void {
    $setup = createSellerShopVariant();
    $otherSeller = createSellerShopVariant();
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $order = createPaidOrderFromSetups([
        ['shop' => $setup['shop'], 'variant' => $setup['variant'], 'subtotal' => '10.00'],
    ]);
    OrderPaid::dispatch($order);

    $shipment = Shipment::query()->where('order_id', $order->id)->sole();
    $exit = (string) $shipment->exit_code;

    $this->actingAs($otherSeller['seller'], 'sanctum');
    $this->postJson(SHIPMENTS_VERIFY_PICKUP_URL, [
        'shipment_id' => $shipment->id,
        'exit_code' => $exit,
    ])->assertForbidden();

    $this->actingAs($customer, 'sanctum');
    $this->postJson(SHIPMENTS_VERIFY_PICKUP_URL, [
        'shipment_id' => $shipment->id,
        'exit_code' => $exit,
    ])->assertForbidden();
});

it('allows admin to verify pickup for any shipment', function (): void {
    $setup = createSellerShopVariant();
    $order = createPaidOrderFromSetups([
        ['shop' => $setup['shop'], 'variant' => $setup['variant'], 'subtotal' => '10.00'],
    ]);
    OrderPaid::dispatch($order);

    $shipment = Shipment::query()->where('order_id', $order->id)->sole();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin, 'sanctum');
    $this->postJson(SHIPMENTS_VERIFY_PICKUP_URL, [
        'shipment_id' => $shipment->id,
        'exit_code' => (string) $shipment->exit_code,
    ])->assertSuccessful();
});

it('requires pickup before Kilora delivery verification', function (): void {
    $setup = createSellerShopVariant();
    $order = createPaidOrderFromSetups([
        ['shop' => $setup['shop'], 'variant' => $setup['variant'], 'subtotal' => '10.00'],
    ]);
    OrderPaid::dispatch($order);

    $shipment = Shipment::query()->where('order_id', $order->id)->sole();
    $this->actingAs($setup['seller'], 'sanctum');

    $this->postJson(SHIPMENTS_VERIFY_DELIVERY_URL, [
        'shipment_id' => $shipment->id,
        'confirmation_code' => (string) $shipment->confirmation_code,
    ])->assertUnprocessable()
        ->assertJsonPath('errors.shipment_id.0', 'Le colis doit être retiré (pickup) avant la livraison finale.');
});

it('rejects invalid confirmation code on delivery verification', function (): void {
    $setup = createSellerShopVariant();
    $order = createPaidOrderFromSetups([
        ['shop' => $setup['shop'], 'variant' => $setup['variant'], 'subtotal' => '10.00'],
    ]);
    OrderPaid::dispatch($order);

    $shipment = Shipment::query()->where('order_id', $order->id)->sole();
    $this->actingAs($setup['seller'], 'sanctum');

    $this->postJson(SHIPMENTS_VERIFY_PICKUP_URL, [
        'shipment_id' => $shipment->id,
        'exit_code' => (string) $shipment->exit_code,
    ])->assertSuccessful();

    $this->postJson(SHIPMENTS_VERIFY_DELIVERY_URL, [
        'shipment_id' => $shipment->id,
        'confirmation_code' => 'WRONG1',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.confirmation_code.0', 'Code de confirmation invalide.');
});

it('rejects delivery verification for cancelled shipments', function (): void {
    $setup = createSellerShopVariant();
    $order = createPaidOrderFromSetups([
        ['shop' => $setup['shop'], 'variant' => $setup['variant'], 'subtotal' => '10.00'],
    ]);
    OrderPaid::dispatch($order);

    $shipment = Shipment::query()->where('order_id', $order->id)->sole();
    $shipment->update(['status' => ShipmentStatus::Cancelled]);

    $this->actingAs($setup['seller'], 'sanctum');
    $this->postJson(SHIPMENTS_VERIFY_DELIVERY_URL, [
        'shipment_id' => $shipment->id,
        'confirmation_code' => (string) $shipment->confirmation_code,
    ])->assertUnprocessable()
        ->assertJsonPath('errors.shipment_id.0', 'Cette expédition est annulée.');
});

it('allows idempotent delivery verification with the same confirmation code', function (): void {
    $setup = createSellerShopVariant();
    $order = createPaidOrderFromSetups([
        ['shop' => $setup['shop'], 'variant' => $setup['variant'], 'subtotal' => '10.00'],
    ]);
    OrderPaid::dispatch($order);

    $shipment = Shipment::query()->where('order_id', $order->id)->sole();
    $confirm = (string) $shipment->confirmation_code;
    $this->actingAs($setup['seller'], 'sanctum');

    $this->postJson(SHIPMENTS_VERIFY_PICKUP_URL, [
        'shipment_id' => $shipment->id,
        'exit_code' => (string) $shipment->exit_code,
    ])->assertSuccessful();

    $this->postJson(SHIPMENTS_VERIFY_DELIVERY_URL, [
        'shipment_id' => $shipment->id,
        'confirmation_code' => $confirm,
    ])->assertSuccessful();

    $this->postJson(SHIPMENTS_VERIFY_DELIVERY_URL, [
        'shipment_id' => $shipment->id,
        'confirmation_code' => $confirm,
    ])->assertSuccessful();

    expect($shipment->fresh()->status)->toBe(ShipmentStatus::Delivered);
});

it('keeps order processing until all multi-shop shipments are delivered', function (): void {
    $a = createSellerShopVariant('10.00');
    $b = createSellerShopVariant('20.00');
    $order = createPaidOrderFromSetups([
        ['shop' => $a['shop'], 'variant' => $a['variant'], 'subtotal' => '10.00'],
        ['shop' => $b['shop'], 'variant' => $b['variant'], 'subtotal' => '20.00'],
    ]);
    OrderPaid::dispatch($order);

    $shipments = Shipment::query()->where('order_id', $order->id)->get()->keyBy('shop_id');
    $shipmentA = $shipments[(int) $a['shop']->id];

    $this->actingAs($a['seller'], 'sanctum');
    $this->postJson(SHIPMENTS_VERIFY_PICKUP_URL, [
        'shipment_id' => $shipmentA->id,
        'exit_code' => (string) $shipmentA->exit_code,
    ])->assertSuccessful();
    $this->postJson(SHIPMENTS_VERIFY_DELIVERY_URL, [
        'shipment_id' => $shipmentA->id,
        'confirmation_code' => (string) $shipmentA->confirmation_code,
    ])->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::Processing);

    $shipmentB = $shipments[(int) $b['shop']->id];
    $this->actingAs($b['seller'], 'sanctum');
    $this->postJson(SHIPMENTS_VERIFY_PICKUP_URL, [
        'shipment_id' => $shipmentB->id,
        'exit_code' => (string) $shipmentB->exit_code,
    ])->assertSuccessful();
    $this->postJson(SHIPMENTS_VERIFY_DELIVERY_URL, [
        'shipment_id' => $shipmentB->id,
        'confirmation_code' => (string) $shipmentB->confirmation_code,
    ])->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
});

it('marks order shipped when only non-cancelled shipments are delivered', function (): void {
    $a = createSellerShopVariant('10.00');
    $b = createSellerShopVariant('20.00');
    $order = createPaidOrderFromSetups([
        ['shop' => $a['shop'], 'variant' => $a['variant'], 'subtotal' => '10.00'],
        ['shop' => $b['shop'], 'variant' => $b['variant'], 'subtotal' => '20.00'],
    ]);
    OrderPaid::dispatch($order);

    $shipments = Shipment::query()->where('order_id', $order->id)->get()->keyBy('shop_id');
    $shipmentA = $shipments[(int) $a['shop']->id];
    $shipmentB = $shipments[(int) $b['shop']->id];
    $shipmentB->update(['status' => ShipmentStatus::Cancelled]);

    $this->actingAs($a['seller'], 'sanctum');
    $this->postJson(SHIPMENTS_VERIFY_PICKUP_URL, [
        'shipment_id' => $shipmentA->id,
        'exit_code' => (string) $shipmentA->exit_code,
    ])->assertSuccessful();
    $this->postJson(SHIPMENTS_VERIFY_DELIVERY_URL, [
        'shipment_id' => $shipmentA->id,
        'confirmation_code' => (string) $shipmentA->confirmation_code,
    ])->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
});

it('keeps Kilora payout pending until pickup and releases it after pickup only', function (): void {
    $setup = createSellerShopVariant('100.00');
    $order = createPaidOrderFromSetups([
        ['shop' => $setup['shop'], 'variant' => $setup['variant'], 'subtotal' => '100.00'],
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

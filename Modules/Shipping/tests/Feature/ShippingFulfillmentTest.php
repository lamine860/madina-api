<?php

declare(strict_types=1);

use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Core\Enums\UserRole;
use Modules\Core\Models\User;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Events\OrderPaid;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderItem;
use Modules\Payouts\Enums\PayoutStatus;
use Modules\Payouts\Models\Payout;
use Modules\Payouts\Services\PayoutService;
use Modules\Shipping\Enums\DeliveryMode;
use Modules\Shipping\Enums\DeliveryProviderType;
use Modules\Shipping\Enums\ShipmentStatus;
use Modules\Shipping\Models\DeliveryProvider;
use Modules\Shipping\Models\Shipment;
use Modules\Shipping\Models\ShippingRate;
use Modules\Shop\Models\Shop;

const SHIPPING_OPTIONS_URL = '/api/v1/shipping/options';
const SHIPMENTS_VERIFY_PICKUP_URL = '/api/v1/shipments/verify-pickup';
const SHIPMENTS_VERIFY_DELIVERY_URL = '/api/v1/shipments/verify-delivery';

/**
 * @return array{shop: Shop, variant: ProductVariant, seller: User}
 */
function createSellerShopVariant(string $price = '10.00'): array
{
    $seller = User::factory()->create(['role' => UserRole::Seller]);
    $shop = Shop::factory()->create(['user_id' => $seller->id]);
    $category = Category::query()->create([
        'name' => 'Cat',
        'slug' => 'cat-'.uniqid(),
        'parent_id' => null,
    ]);
    $product = Product::query()->create([
        'shop_id' => $shop->id,
        'category_id' => $category->id,
        'name' => 'P',
        'slug' => 'p-'.uniqid(),
        'base_price' => 10,
        'is_active' => true,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'SKU-'.uniqid(),
        'price' => $price,
        'stock_qty' => 10,
        'attributes' => [],
    ]);

    return ['shop' => $shop, 'variant' => $variant, 'seller' => $seller];
}

it('returns shipping options for Zone A and Zone B neighborhoods', function (): void {
    $user = User::factory()->create(['role' => UserRole::Customer]);
    $this->actingAs($user, 'sanctum');

    $this->getJson(SHIPPING_OPTIONS_URL.'?neighborhood_slug=madina')
        ->assertSuccessful()
        ->assertJsonPath('zone', 'Zone A');

    $flashZoneA = collect($this->getJson(SHIPPING_OPTIONS_URL.'?neighborhood_slug=madina')->json('options'))
        ->firstWhere('code', 'FLASH');
    expect($flashZoneA['eta_min_minutes'])->toBe(90)
        ->and($flashZoneA['eta_max_minutes'])->toBe(150);

    $flashKoloma = collect($this->getJson(SHIPPING_OPTIONS_URL.'?neighborhood_slug=koloma')->json('options'))
        ->firstWhere('code', 'FLASH');
    expect($flashKoloma['eta_min_minutes'])->toBe(150)
        ->and($flashKoloma['eta_max_minutes'])->toBe(240);
});

it('creates shipments and payouts for each shop when an order is paid', function (): void {
    $a = createSellerShopVariant('10.00');
    $b = createSellerShopVariant('20.00');
    $buyer = User::factory()->create(['role' => UserRole::Customer]);

    $order = Order::query()->create([
        'order_number' => 'CMD-T-'.uniqid(),
        'user_id' => $buyer->id,
        'total_amount' => '30.00',
        'status' => OrderStatus::Paid,
        'shipping_address' => ['line1' => '1', 'city' => 'Conakry', 'postal_code' => 'GN', 'country' => 'GN'],
        'billing_address' => null,
        'notes' => null,
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'shop_id' => $a['shop']->id,
        'product_variant_id' => $a['variant']->id,
        'quantity' => 1,
        'unit_price' => '10.00',
        'subtotal' => '10.00',
    ]);
    OrderItem::query()->create([
        'order_id' => $order->id,
        'shop_id' => $b['shop']->id,
        'product_variant_id' => $b['variant']->id,
        'quantity' => 1,
        'unit_price' => '20.00',
        'subtotal' => '20.00',
    ]);

    OrderPaid::dispatch($order->fresh());

    expect(Shipment::query()->where('order_id', $order->id)->count())->toBe(2)
        ->and(Payout::query()->where('order_id', $order->id)->count())->toBe(2);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Processing);
});

it('releases payout on Kilora pickup and completes order when all shops deliver', function (): void {
    $a = createSellerShopVariant('100.00');
    $buyer = User::factory()->create(['role' => UserRole::Customer]);

    $order = Order::query()->create([
        'order_number' => 'CMD-K-'.uniqid(),
        'user_id' => $buyer->id,
        'total_amount' => '100.00',
        'status' => OrderStatus::Paid,
        'shipping_address' => ['line1' => '1', 'city' => 'Conakry', 'postal_code' => 'GN', 'country' => 'GN'],
        'billing_address' => null,
        'notes' => null,
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'shop_id' => $a['shop']->id,
        'product_variant_id' => $a['variant']->id,
        'quantity' => 1,
        'unit_price' => '100.00',
        'subtotal' => '100.00',
    ]);

    OrderPaid::dispatch($order->fresh());

    /** @var Shipment $shipment */
    $shipment = Shipment::query()->where('order_id', $order->id)->sole();
    $exit = (string) $shipment->exit_code;
    $confirm = (string) $shipment->confirmation_code;

    $this->actingAs($a['seller'], 'sanctum');

    $this->postJson(SHIPMENTS_VERIFY_PICKUP_URL, [
        'shipment_id' => $shipment->id,
        'exit_code' => $exit,
    ])->assertSuccessful();

    $payout = Payout::query()->where('order_id', $order->id)->where('shop_id', $a['shop']->id)->sole();
    expect($payout->status)->toBe(PayoutStatus::Ready);

    $this->postJson(SHIPMENTS_VERIFY_PICKUP_URL, [
        'shipment_id' => $shipment->id,
        'exit_code' => $exit,
    ])->assertSuccessful();

    $this->postJson(SHIPMENTS_VERIFY_DELIVERY_URL, [
        'shipment_id' => $shipment->id,
        'confirmation_code' => $confirm,
    ])->assertSuccessful();

    expect($shipment->fresh()->status)->toBe(ShipmentStatus::Delivered)
        ->and($order->fresh()->status)->toBe(OrderStatus::Shipped);
});

it('releases payout on delivery only for shop self-delivery', function (): void {
    $a = createSellerShopVariant('50.00');
    $buyer = User::factory()->create(['role' => UserRole::Customer]);

    $order = Order::query()->create([
        'order_number' => 'CMD-S-'.uniqid(),
        'user_id' => $buyer->id,
        'total_amount' => '50.00',
        'status' => OrderStatus::Paid,
        'shipping_address' => ['line1' => '1', 'city' => 'Conakry', 'postal_code' => 'GN', 'country' => 'GN'],
        'billing_address' => null,
        'notes' => null,
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'shop_id' => $a['shop']->id,
        'product_variant_id' => $a['variant']->id,
        'quantity' => 1,
        'unit_price' => '50.00',
        'subtotal' => '50.00',
    ]);

    /** @var DeliveryProvider $shopProvider */
    $shopProvider = DeliveryProvider::query()->where('type', DeliveryProviderType::Shop)->sole();
    /** @var ShippingRate $service */
    $service = ShippingRate::query()->where('code', 'ECO')->sole();

    app(PayoutService::class)->createPendingForShopIfMissing($order, (int) $a['shop']->id);

    $shipment = Shipment::query()->create([
        'order_id' => $order->id,
        'shop_id' => $a['shop']->id,
        'provider_id' => $shopProvider->id,
        'service_id' => $service->id,
        'exit_code' => null,
        'confirmation_code' => 'ABCDEF',
        'status' => ShipmentStatus::Pending,
        'delivery_mode' => DeliveryMode::ShopSelfDelivery,
    ]);

    $this->actingAs($a['seller'], 'sanctum');

    $this->postJson(SHIPMENTS_VERIFY_PICKUP_URL, [
        'shipment_id' => $shipment->id,
        'exit_code' => 'IGNORE',
    ])->assertStatus(422);

    $this->postJson(SHIPMENTS_VERIFY_DELIVERY_URL, [
        'shipment_id' => $shipment->id,
        'confirmation_code' => 'ABCDEF',
    ])->assertSuccessful();

    $payout = Payout::query()->where('order_id', $order->id)->sole();
    expect($payout->status)->toBe(PayoutStatus::Ready);
});

it('rejects logistics verification when the order is not paid', function (): void {
    $a = createSellerShopVariant();
    $buyer = User::factory()->create(['role' => UserRole::Customer]);

    $order = Order::query()->create([
        'order_number' => 'CMD-P-'.uniqid(),
        'user_id' => $buyer->id,
        'total_amount' => '10.00',
        'status' => OrderStatus::Pending,
        'shipping_address' => ['line1' => '1', 'city' => 'Conakry', 'postal_code' => 'GN', 'country' => 'GN'],
        'billing_address' => null,
        'notes' => null,
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'shop_id' => $a['shop']->id,
        'product_variant_id' => $a['variant']->id,
        'quantity' => 1,
        'unit_price' => '10.00',
        'subtotal' => '10.00',
    ]);

    $kilora = DeliveryProvider::query()->where('type', DeliveryProviderType::Internal)->sole();
    $service = ShippingRate::query()->where('code', 'FLASH')->sole();

    $shipment = Shipment::query()->create([
        'order_id' => $order->id,
        'shop_id' => $a['shop']->id,
        'provider_id' => $kilora->id,
        'service_id' => $service->id,
        'exit_code' => 'ZZZZZZ',
        'confirmation_code' => 'YYYYYY',
        'status' => ShipmentStatus::Pending,
        'delivery_mode' => DeliveryMode::KiloraDelivery,
    ]);

    $this->actingAs($a['seller'], 'sanctum');

    $this->postJson(SHIPMENTS_VERIFY_PICKUP_URL, [
        'shipment_id' => $shipment->id,
        'exit_code' => 'ZZZZZZ',
    ])->assertStatus(422)
        ->assertJsonPath('errors.order.0', 'La commande doit être payée avant cette opération de logistique.');
});

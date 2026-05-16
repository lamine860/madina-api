<?php

declare(strict_types=1);

use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Core\Enums\UserRole;
use Modules\Core\Models\User;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderItem;
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

/**
 * @param  list<array{shop: Shop, variant: ProductVariant, unit_price: string, subtotal: string, quantity?: int}>  $lines
 */
function createOrderWithItems(array $lines, OrderStatus $status = OrderStatus::Paid, ?User $buyer = null): Order
{
    $buyer ??= User::factory()->create(['role' => UserRole::Customer]);
    $total = '0.00';

    foreach ($lines as $line) {
        $total = bcadd($total, $line['subtotal'], 2);
    }

    $order = Order::query()->create([
        'order_number' => 'CMD-'.uniqid(),
        'user_id' => $buyer->id,
        'total_amount' => $total,
        'status' => $status,
        'shipping_address' => ['line1' => '1', 'city' => 'Conakry', 'postal_code' => 'GN', 'country' => 'GN'],
        'billing_address' => null,
        'notes' => null,
    ]);

    foreach ($lines as $line) {
        OrderItem::query()->create([
            'order_id' => $order->id,
            'shop_id' => $line['shop']->id,
            'product_variant_id' => $line['variant']->id,
            'quantity' => $line['quantity'] ?? 1,
            'unit_price' => $line['unit_price'],
            'subtotal' => $line['subtotal'],
        ]);
    }

    return $order->fresh();
}

/**
 * @param  list<array{shop: Shop, variant: ProductVariant, unit_price?: string, subtotal?: string}>  $setups
 */
function createPaidOrderFromSetups(array $setups): Order
{
    $lines = [];

    foreach ($setups as $setup) {
        $subtotal = $setup['subtotal'] ?? $setup['unit_price'] ?? '10.00';
        $lines[] = [
            'shop' => $setup['shop'],
            'variant' => $setup['variant'],
            'unit_price' => $setup['unit_price'] ?? $subtotal,
            'subtotal' => $subtotal,
        ];
    }

    return createOrderWithItems($lines, OrderStatus::Paid);
}

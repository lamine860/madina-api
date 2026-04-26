<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Modules\Cart\Models\CartItem;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Core\Enums\UserRole;
use Modules\Core\Models\User;
use Modules\Orders\Events\OrderCreated;
use Modules\Orders\Models\Order;
use Modules\Shop\Models\Shop;

const ORDERS_API = '/api/v1/orders';
const CART_CHECKOUT = '/api/v1/cart';

/**
 * @return array{shop: Shop, variant: ProductVariant}
 */
function createShopWithVariant(int $stock = 10, string $price = '10.00'): array
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
        'stock_qty' => $stock,
        'attributes' => [],
    ]);

    return ['shop' => $shop, 'variant' => $variant];
}

it('checkout creates a multi-shop order and clears the cart', function () {
    Event::fake([OrderCreated::class]);

    $a = createShopWithVariant();
    $b = createShopWithVariant();
    $buyer = User::factory()->create(['role' => UserRole::Customer]);

    $this->actingAs($buyer, 'sanctum');
    $this->postJson(CART_CHECKOUT, [
        'product_variant_id' => $a['variant']->id,
        'quantity' => 1,
    ])->assertCreated();
    $this->postJson(CART_CHECKOUT, [
        'product_variant_id' => $b['variant']->id,
        'quantity' => 2,
    ])->assertCreated();

    $payload = [
        'shipping_address' => [
            'line1' => '1 rue Test',
            'city' => 'Paris',
            'postal_code' => '75001',
            'country' => 'FR',
        ],
        'notes' => 'Merci',
    ];

    $response = $this->postJson(ORDERS_API.'/checkout', $payload);

    $response->assertCreated()
        ->assertJsonPath('order.status', 'pending')
        ->assertJsonCount(2, 'order.items');

    $shopIds = collect($response->json('order.items'))->pluck('shop_id')->unique()->sort()->values()->all();
    expect($shopIds)->toBe([(int) $a['shop']->id, (int) $b['shop']->id]);

    expect((string) $response->json('order.total_amount'))->toBe('30.00');

    $this->getJson(CART_CHECKOUT)->assertJsonCount(0, 'items');

    Event::assertDispatched(OrderCreated::class);

    expect($a['variant']->refresh()->stock_qty)->toBe(9);
    expect($b['variant']->refresh()->stock_qty)->toBe(8);
});

it('does not create an order when stock is insufficient and leaves the cart intact', function () {
    $a = createShopWithVariant(stock: 1);
    $buyer = User::factory()->create(['role' => UserRole::Customer]);

    $this->actingAs($buyer, 'sanctum');
    $this->postJson(CART_CHECKOUT, [
        'product_variant_id' => $a['variant']->id,
        'quantity' => 1,
    ])->assertCreated();

    CartItem::query()->where('user_id', $buyer->id)->update(['quantity' => 3]);

    $this->postJson(ORDERS_API.'/checkout', [
        'shipping_address' => [
            'line1' => '1 rue',
            'city' => 'Lyon',
            'postal_code' => '69001',
            'country' => 'FR',
        ],
    ])->assertStatus(422);

    expect(Order::query()->count())->toBe(0);
    $this->getJson(CART_CHECKOUT)->assertJsonCount(1, 'items');
});

it('returns 404 when another user opens an order detail', function () {
    $a = createShopWithVariant();
    $buyer = User::factory()->create(['role' => UserRole::Customer]);
    $other = User::factory()->create(['role' => UserRole::Customer]);

    $this->actingAs($buyer, 'sanctum');
    $this->postJson(CART_CHECKOUT, [
        'product_variant_id' => $a['variant']->id,
        'quantity' => 1,
    ])->assertCreated();

    $orderId = $this->postJson(ORDERS_API.'/checkout', [
        'shipping_address' => [
            'line1' => '1 rue',
            'city' => 'Marseille',
            'postal_code' => '13001',
            'country' => 'FR',
        ],
    ])->assertCreated()->json('order.id');

    $this->actingAs($other, 'sanctum');
    $this->getJson(ORDERS_API.'/'.$orderId)->assertNotFound();
});

it('lets a shop owner list orders with only their line items', function () {
    $a = createShopWithVariant();
    $b = createShopWithVariant();
    $buyer = User::factory()->create(['role' => UserRole::Customer]);

    $this->actingAs($buyer, 'sanctum');
    $this->postJson(CART_CHECKOUT, [
        'product_variant_id' => $a['variant']->id,
        'quantity' => 1,
    ])->assertCreated();
    $this->postJson(CART_CHECKOUT, [
        'product_variant_id' => $b['variant']->id,
        'quantity' => 1,
    ])->assertCreated();

    $this->postJson(ORDERS_API.'/checkout', [
        'shipping_address' => [
            'line1' => '1 rue',
            'city' => 'Nice',
            'postal_code' => '06000',
            'country' => 'FR',
        ],
    ])->assertCreated();

    $sellerA = User::query()->whereKey($a['shop']->user_id)->firstOrFail();
    $this->actingAs($sellerA, 'sanctum');
    $response = $this->getJson('/api/v1/shops/'.$a['shop']->id.'/orders');

    $response->assertSuccessful();
    expect($response->json('data.0.items'))->toHaveCount(1);
    expect($response->json('data.0.items.0.shop_id'))->toBe($a['shop']->id);
});

it('allows admin to update order status', function () {
    $a = createShopWithVariant();
    $buyer = User::factory()->create(['role' => UserRole::Customer]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($buyer, 'sanctum');
    $this->postJson(CART_CHECKOUT, [
        'product_variant_id' => $a['variant']->id,
        'quantity' => 1,
    ])->assertCreated();

    $orderId = $this->postJson(ORDERS_API.'/checkout', [
        'shipping_address' => [
            'line1' => '1 rue',
            'city' => 'Bordeaux',
            'postal_code' => '33000',
            'country' => 'FR',
        ],
    ])->assertCreated()->json('order.id');

    $this->actingAs($admin, 'sanctum');
    $this->patchJson(ORDERS_API.'/'.$orderId.'/status', [
        'status' => 'paid',
    ])->assertSuccessful()
        ->assertJsonPath('order.status', 'paid');
});

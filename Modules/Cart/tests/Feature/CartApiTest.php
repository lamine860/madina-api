<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Core\Models\User;
use Modules\Shop\Models\Shop;

const CART_API = '/api/v1/cart';
const WISHLIST_API = '/api/v1/wishlist';

function createVariantForCartTest(int $stock = 10, string $price = '12.50'): ProductVariant
{
    $user = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $category = Category::query()->create([
        'name' => 'Cat',
        'slug' => 'cat-'.uniqid(),
        'parent_id' => null,
    ]);
    $product = Product::query()->create([
        'shop_id' => $shop->id,
        'category_id' => $category->id,
        'name' => 'Produit',
        'slug' => 'p-'.uniqid(),
        'base_price' => 10,
        'is_active' => true,
    ]);

    return ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'SKU-'.uniqid(),
        'price' => $price,
        'stock_qty' => $stock,
        'attributes' => [],
    ]);
}

it('requires authentication for cart', function () {
    test()->getJson(CART_API, ['Accept' => 'application/json'])->assertUnauthorized();
});

it('adds to cart and returns total', function () {
    $variant = createVariantForCartTest();
    $buyer = User::factory()->create(['password' => Hash::make('Passw0rd!')]);
    $token = $buyer->createToken('t')->plainTextToken;

    $response = test()->withToken($token)->postJson(CART_API, [
        'product_variant_id' => $variant->id,
        'quantity' => 2,
    ], ['Accept' => 'application/json']);

    $response->assertCreated()
        ->assertJsonPath('item.quantity', 2)
        ->assertJsonPath('item.variant.id', $variant->id)
        ->assertJsonPath('total', '25.00');

    test()->withToken($token)->getJson(CART_API, ['Accept' => 'application/json'])
        ->assertSuccessful()
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('total', '25.00');
});

it('merges quantity when adding the same variant twice', function () {
    $variant = createVariantForCartTest(stock: 20);
    $buyer = User::factory()->create(['password' => Hash::make('Passw0rd!')]);
    $token = $buyer->createToken('t')->plainTextToken;

    test()->withToken($token)->postJson(CART_API, [
        'product_variant_id' => $variant->id,
        'quantity' => 2,
    ])->assertCreated();

    test()->withToken($token)->postJson(CART_API, [
        'product_variant_id' => $variant->id,
        'quantity' => 3,
    ])->assertCreated()
        ->assertJsonPath('item.quantity', 5);
});

it('returns 422 when stock is insufficient', function () {
    $variant = createVariantForCartTest(stock: 1);
    $buyer = User::factory()->create(['password' => Hash::make('Passw0rd!')]);
    $token = $buyer->createToken('t')->plainTextToken;

    test()->withToken($token)->postJson(CART_API, [
        'product_variant_id' => $variant->id,
        'quantity' => 5,
    ])->assertStatus(422)
        ->assertJsonStructure(['message', 'errors']);
});

it('returns 404 when another user targets a cart item', function () {
    $variant = createVariantForCartTest();
    $owner = User::factory()->create(['password' => Hash::make('Passw0rd!')]);
    $intruder = User::factory()->create(['password' => Hash::make('Passw0rd!')]);

    $this->actingAs($owner, 'sanctum');
    $itemId = $this->postJson(CART_API, [
        'product_variant_id' => $variant->id,
        'quantity' => 1,
    ])->assertCreated()->json('item.id');

    $this->actingAs($intruder, 'sanctum');
    $this->patchJson(CART_API.'/items/'.$itemId, [
        'quantity' => 99,
    ])->assertNotFound();
});

it('updates cart item quantity', function () {
    $variant = createVariantForCartTest(stock: 10);
    $buyer = User::factory()->create(['password' => Hash::make('Passw0rd!')]);
    $token = $buyer->createToken('t')->plainTextToken;

    $itemId = test()->withToken($token)->postJson(CART_API, [
        'product_variant_id' => $variant->id,
        'quantity' => 2,
    ])->json('item.id');

    test()->withToken($token)->patchJson(CART_API.'/items/'.$itemId, [
        'quantity' => 4,
    ])->assertSuccessful()
        ->assertJsonPath('item.quantity', 4)
        ->assertJsonPath('total', '50.00');
});

it('removes a cart item', function () {
    $variant = createVariantForCartTest();
    $buyer = User::factory()->create(['password' => Hash::make('Passw0rd!')]);
    $token = $buyer->createToken('t')->plainTextToken;

    $itemId = test()->withToken($token)->postJson(CART_API, [
        'product_variant_id' => $variant->id,
        'quantity' => 1,
    ])->json('item.id');

    test()->withToken($token)->deleteJson(CART_API.'/items/'.$itemId)
        ->assertSuccessful()
        ->assertJsonPath('total', '0.00');

    test()->withToken($token)->getJson(CART_API)->assertJsonCount(0, 'items');
});

it('manages wishlist for authenticated user', function () {
    $variant = createVariantForCartTest();
    $user = User::factory()->create(['password' => Hash::make('Passw0rd!')]);
    $token = $user->createToken('t')->plainTextToken;

    $id = test()->withToken($token)->postJson(WISHLIST_API, [
        'product_variant_id' => $variant->id,
    ])->assertCreated()->json('item.id');

    test()->withToken($token)->getJson(WISHLIST_API)
        ->assertSuccessful()
        ->assertJsonCount(1, 'items');

    test()->withToken($token)->deleteJson(WISHLIST_API.'/'.$id)->assertNoContent();

    test()->withToken($token)->getJson(WISHLIST_API)->assertJsonCount(0, 'items');
});

it('returns 404 when another user targets a wishlist row', function () {
    $variant = createVariantForCartTest();
    $owner = User::factory()->create(['password' => Hash::make('Passw0rd!')]);
    $intruder = User::factory()->create(['password' => Hash::make('Passw0rd!')]);

    $this->actingAs($owner, 'sanctum');
    $id = $this->postJson(WISHLIST_API, [
        'product_variant_id' => $variant->id,
    ])->assertCreated()->json('item.id');

    $this->actingAs($intruder, 'sanctum');
    $this->deleteJson(WISHLIST_API.'/'.$id)->assertNotFound();
});

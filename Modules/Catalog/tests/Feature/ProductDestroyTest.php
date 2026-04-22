<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Modules\Catalog\Entities\Category;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Entities\ProductImage;
use Modules\Catalog\Entities\ProductVariant;
use Modules\Core\Entities\User;
use Modules\Shop\Entities\Shop;

const PRODUCTS_DESTROY_BASE = '/api/v1/shops';

it('soft deletes product and variants for shop owner', function () {
    $user = User::factory()->create([
        'password' => Hash::make('Passw0rd!'),
    ]);
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $category = Category::query()->create([
        'name' => 'Cat',
        'slug' => 'cat-'.uniqid(),
        'parent_id' => null,
    ]);

    $product = Product::query()->create([
        'shop_id' => $shop->id,
        'category_id' => $category->id,
        'name' => 'P soft',
        'slug' => 'p-soft-'.uniqid(),
        'base_price' => 10,
        'is_active' => true,
    ]);

    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'SKU-SOFT-'.uniqid(),
        'price' => 10,
        'stock_qty' => 1,
        'attributes' => ['a' => 'b'],
    ]);

    $token = $user->createToken('t')->plainTextToken;

    test()->withToken($token)->deleteJson(PRODUCTS_DESTROY_BASE.'/'.$shop->id.'/products/'.$product->id)
        ->assertNoContent();

    expect(Product::query()->whereKey($product->id)->exists())->toBeFalse();
    expect(Product::withTrashed()->whereKey($product->id)->exists())->toBeTrue();
    expect(ProductVariant::withTrashed()->whereKey($variant->id)->exists())->toBeTrue();
    expect(ProductVariant::query()->whereKey($variant->id)->exists())->toBeFalse();
});

it('hard deletes product images from disk and database', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'password' => Hash::make('Passw0rd!'),
    ]);
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $category = Category::query()->create([
        'name' => 'Cat2',
        'slug' => 'cat2-'.uniqid(),
        'parent_id' => null,
    ]);

    $token = $user->createToken('t')->plainTextToken;

    $response = test()->withToken($token)->post(PRODUCTS_DESTROY_BASE.'/'.$shop->id.'/products', [
        'name' => 'Avec images',
        'base_price' => 12,
        'category_id' => $category->id,
        'variants' => json_encode([
            [
                'sku' => 'SKU-HARD-'.uniqid(),
                'price' => 12,
                'stock_qty' => 1,
                'attributes' => ['type' => 'x'],
            ],
        ]),
        'gallery' => [UploadedFile::fake()->image('x.jpg', 40, 40)],
    ], ['Accept' => 'application/json']);

    $response->assertCreated();
    $productId = (int) $response->json('product.id');

    Storage::disk('public')->assertExists('products/'.$productId.'/original');

    test()->withToken($token)->delete(PRODUCTS_DESTROY_BASE.'/'.$shop->id.'/products/'.$productId.'?force=1', [], [
        'Accept' => 'application/json',
    ])->assertNoContent();

    expect(Product::withTrashed()->whereKey($productId)->exists())->toBeFalse();
    expect(ProductImage::query()->where('product_id', $productId)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing('products/'.$productId);
});

it('forbids delete for user who does not own the shop', function () {
    $owner = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $owner->id]);
    $category = Category::query()->create([
        'name' => 'C',
        'slug' => 'c-'.uniqid(),
        'parent_id' => null,
    ]);
    $product = Product::query()->create([
        'shop_id' => $shop->id,
        'category_id' => $category->id,
        'name' => 'P',
        'slug' => 'p-'.uniqid(),
        'base_price' => 1,
        'is_active' => true,
    ]);

    $other = User::factory()->create([
        'password' => Hash::make('Passw0rd!'),
    ]);
    $token = $other->createToken('t')->plainTextToken;

    test()->withToken($token)->deleteJson(PRODUCTS_DESTROY_BASE.'/'.$shop->id.'/products/'.$product->id)
        ->assertForbidden();
});

it('allows admin to delete another users product', function () {
    $owner = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $owner->id]);
    $category = Category::query()->create([
        'name' => 'Cadm',
        'slug' => 'cadm-'.uniqid(),
        'parent_id' => null,
    ]);
    $product = Product::query()->create([
        'shop_id' => $shop->id,
        'category_id' => $category->id,
        'name' => 'Padm',
        'slug' => 'padm-'.uniqid(),
        'base_price' => 1,
        'is_active' => true,
    ]);

    $admin = User::factory()->admin()->create([
        'password' => Hash::make('Passw0rd!'),
    ]);
    $token = $admin->createToken('t')->plainTextToken;

    test()->withToken($token)->deleteJson(PRODUCTS_DESTROY_BASE.'/'.$shop->id.'/products/'.$product->id)
        ->assertNoContent();

    expect(Product::withTrashed()->whereKey($product->id)->exists())->toBeTrue();
});

<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Modules\Catalog\Entities\Category;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Entities\ProductImage;
use Modules\Core\Entities\User;
use Modules\Shop\Entities\Shop;

const CATALOG_PRODUCTS_BASE = '/api/v1/shops';

it('persists product gallery rows and files on create', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'password' => Hash::make('ValidPass1!'),
    ]);
    $token = $user->createToken('test')->plainTextToken;

    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $category = Category::query()->create([
        'name' => 'Cat Test',
        'slug' => 'cat-test',
        'parent_id' => null,
    ]);

    $variantsPayload = [
        [
            'sku' => 'GAL-SKU-1',
            'price' => 19.99,
            'stock_qty' => 3,
            'attributes' => ['color' => 'blue'],
        ],
    ];

    $uri = CATALOG_PRODUCTS_BASE.'/'.$shop->id.'/products';

    $response = test()->withToken($token)->post($uri, [
        'name' => 'Produit avec galerie',
        'base_price' => 19.99,
        'category_id' => $category->id,
        'variants' => json_encode($variantsPayload),
        'gallery' => [
            UploadedFile::fake()->image('one.jpg', 90, 90),
            UploadedFile::fake()->image('two.png', 80, 80),
        ],
    ], [
        'Accept' => 'application/json',
    ]);

    $response->assertCreated();

    $product = Product::query()->where('shop_id', $shop->id)->firstOrFail();
    expect(ProductImage::query()->where('product_id', $product->id)->count())->toBe(2);

    $featured = ProductImage::query()->where('product_id', $product->id)->where('is_featured', true)->count();
    expect($featured)->toBe(1);

    $first = ProductImage::query()->where('product_id', $product->id)->orderBy('sort_order')->firstOrFail();
    expect($first->is_featured)->toBeTrue();
    Storage::disk('public')->assertExists($first->relativePathOriginal());
    Storage::disk('public')->assertExists($first->relativePathThumbnail());
    Storage::disk('public')->assertExists($first->relativePathLarge());

    $response->assertJsonPath('product.gallery.0.is_featured', true);
    $response->assertJsonPath('product.gallery.1.is_featured', false);
    $response->assertJsonStructure([
        'product' => [
            'gallery' => [
                '*' => ['id', 'filename', 'urls' => ['original', 'thumbnail', 'large'], 'url', 'is_featured', 'sort_order'],
            ],
        ],
    ]);
});

it('creates a product without gallery when gallery is omitted', function () {
    $user = User::factory()->create([
        'password' => Hash::make('ValidPass1!'),
    ]);
    $token = $user->createToken('test')->plainTextToken;

    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $category = Category::query()->create([
        'name' => 'Cat 2',
        'slug' => 'cat-2',
        'parent_id' => null,
    ]);

    $uri = CATALOG_PRODUCTS_BASE.'/'.$shop->id.'/products';

    test()->withToken($token)->post($uri, [
        'name' => 'Sans images',
        'base_price' => 5,
        'category_id' => $category->id,
        'variants' => json_encode([
            [
                'sku' => 'NO-IMG-1',
                'price' => 5,
                'stock_qty' => 1,
                'attributes' => ['type' => 'simple'],
            ],
        ]),
    ], [
        'Accept' => 'application/json',
    ])
        ->assertCreated()
        ->assertJsonPath('product.gallery', []);

    $product = Product::query()->where('shop_id', $shop->id)->firstOrFail();
    expect(ProductImage::query()->where('product_id', $product->id)->count())->toBe(0);
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Product;
use Modules\Core\Models\User;
use Modules\Shop\Models\Shop;

const CATEGORIES_API = '/api/v1/categories';

it('lists root categories only with nested children', function () {
    $root = Category::query()->create([
        'name' => 'Racine A',
        'slug' => 'racine-a',
        'parent_id' => null,
    ]);
    Category::query()->create([
        'name' => 'Enfant',
        'slug' => 'enfant-de-a',
        'parent_id' => $root->id,
    ]);
    Category::query()->create([
        'name' => 'Orpheline racine',
        'slug' => 'racine-b',
        'parent_id' => null,
    ]);

    $response = test()->getJson(CATEGORIES_API, ['Accept' => 'application/json']);

    $response->assertSuccessful();
    expect($response->json('categories'))->toHaveCount(2);
    $racineA = collect($response->json('categories'))->firstWhere('slug', 'racine-a');
    expect($racineA['children'])->toHaveCount(1);
    expect($racineA['children'][0]['slug'])->toBe('enfant-de-a');
});

it('shows a category with breadcrumb and direct children', function () {
    $root = Category::query()->create([
        'name' => 'Maison',
        'slug' => 'maison',
        'parent_id' => null,
    ]);
    $child = Category::query()->create([
        'name' => 'Cuisine',
        'slug' => 'cuisine',
        'parent_id' => $root->id,
    ]);

    $response = test()->getJson(CATEGORIES_API.'/cuisine', ['Accept' => 'application/json']);

    $response->assertSuccessful()
        ->assertJsonPath('category.slug', 'cuisine')
        ->assertJsonPath('breadcrumb.0.slug', 'maison')
        ->assertJsonPath('breadcrumb.1.slug', 'cuisine');
});

it('allows admin to create a category with auto slug', function () {
    $admin = User::factory()->admin()->create([
        'password' => Hash::make('Passw0rd!'),
    ]);
    $token = $admin->createToken('t')->plainTextToken;

    test()->withToken($token)->postJson(CATEGORIES_API, [
        'name' => 'Nouvelle catégorie',
    ])->assertCreated()
        ->assertJsonPath('category.name', 'Nouvelle catégorie')
        ->assertJsonPath('category.slug', 'nouvelle-categorie');

    $this->assertDatabaseHas('categories', [
        'slug' => 'nouvelle-categorie',
        'parent_id' => null,
    ]);
});

it('forbids category create for non admin', function () {
    $user = User::factory()->create([
        'password' => Hash::make('Passw0rd!'),
    ]);
    $token = $user->createToken('t')->plainTextToken;

    test()->withToken($token)->postJson(CATEGORIES_API, [
        'name' => 'Hack',
    ])->assertForbidden();
});

it('updates name slug and parent for admin', function () {
    $admin = User::factory()->admin()->create([
        'password' => Hash::make('Passw0rd!'),
    ]);
    $token = $admin->createToken('t')->plainTextToken;

    $parent = Category::query()->create([
        'name' => 'Parent',
        'slug' => 'parent-cat',
        'parent_id' => null,
    ]);
    $cat = Category::query()->create([
        'name' => 'Enfant',
        'slug' => 'enfant-cat',
        'parent_id' => null,
    ]);

    test()->withToken($token)->putJson(CATEGORIES_API.'/enfant-cat', [
        'name' => 'Enfant renommé',
        'slug' => 'enfant-renomme',
        'parent_id' => $parent->id,
    ])->assertSuccessful()
        ->assertJsonPath('category.slug', 'enfant-renomme')
        ->assertJsonPath('category.parent_id', $parent->id);
});

it('rejects parent_id pointing to a descendant', function () {
    $admin = User::factory()->admin()->create([
        'password' => Hash::make('Passw0rd!'),
    ]);
    $token = $admin->createToken('t')->plainTextToken;

    $root = Category::query()->create([
        'name' => 'R',
        'slug' => 'r-slug',
        'parent_id' => null,
    ]);
    $child = Category::query()->create([
        'name' => 'C',
        'slug' => 'c-slug',
        'parent_id' => $root->id,
    ]);

    test()->withToken($token)->putJson(CATEGORIES_API.'/r-slug', [
        'name' => 'R',
        'parent_id' => $child->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});

it('deletes an empty leaf category', function () {
    $admin = User::factory()->admin()->create([
        'password' => Hash::make('Passw0rd!'),
    ]);
    $token = $admin->createToken('t')->plainTextToken;

    $cat = Category::query()->create([
        'name' => 'À supprimer',
        'slug' => 'a-supprimer',
        'parent_id' => null,
    ]);

    test()->withToken($token)->deleteJson(CATEGORIES_API.'/a-supprimer')
        ->assertNoContent();

    expect(Category::query()->whereKey($cat->id)->exists())->toBeFalse();
});

it('returns 409 when deleting category with children', function () {
    $admin = User::factory()->admin()->create([
        'password' => Hash::make('Passw0rd!'),
    ]);
    $token = $admin->createToken('t')->plainTextToken;

    $root = Category::query()->create([
        'name' => 'Avec enfant',
        'slug' => 'avec-enfant',
        'parent_id' => null,
    ]);
    Category::query()->create([
        'name' => 'Sous',
        'slug' => 'sous-cat',
        'parent_id' => $root->id,
    ]);

    test()->withToken($token)->deleteJson(CATEGORIES_API.'/avec-enfant')
        ->assertStatus(409);
});

it('returns 409 when deleting category linked to products', function () {
    $admin = User::factory()->admin()->create([
        'password' => Hash::make('Passw0rd!'),
    ]);
    $token = $admin->createToken('t')->plainTextToken;

    $shop = Shop::factory()->create();
    $cat = Category::query()->create([
        'name' => 'Liée produit',
        'slug' => 'liee-produit',
        'parent_id' => null,
    ]);

    Product::query()->create([
        'shop_id' => $shop->id,
        'category_id' => $cat->id,
        'name' => 'P',
        'slug' => 'p-unique-'.uniqid(),
        'base_price' => 10,
        'is_active' => true,
    ]);

    test()->withToken($token)->deleteJson(CATEGORIES_API.'/liee-produit')
        ->assertStatus(409);
});

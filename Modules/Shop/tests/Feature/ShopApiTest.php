<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\User;
use Modules\Shop\Models\Shop;

const SHOPS_BASE_URI = '/api/v1/shops';

/**
 * @return array{0: User, 1: string}
 */
function userWithSanctumToken(): array
{
    $user = User::factory()->create([
        'password' => Hash::make('ValidPass1!'),
    ]);

    $token = $user->createToken('test')->plainTextToken;

    return [$user, $token];
}

it('returns a shop by slug without authentication', function () {
    $shop = Shop::factory()->create([
        'slug' => 'madina-tech-store',
        'name' => 'Madina Tech Store',
    ]);

    $this->getJson(SHOPS_BASE_URI.'/'.$shop->slug, [
        'Accept' => 'application/json',
    ])
        ->assertSuccessful()
        ->assertJsonPath('shop.id', $shop->id)
        ->assertJsonPath('shop.slug', 'madina-tech-store')
        ->assertJsonPath('shop.name', 'Madina Tech Store')
        ->assertJsonStructure([
            'shop' => [
                'id',
                'user_id',
                'name',
                'slug',
                'description',
                'logo_url',
                'company_name',
                'vat_number',
                'is_verified',
                'created_at',
                'updated_at',
            ],
        ]);
});

it('returns 404 when shop slug does not exist', function () {
    $this->getJson(SHOPS_BASE_URI.'/unknown-slug-xyz', [
        'Accept' => 'application/json',
    ])
        ->assertNotFound();
});

it('creates a shop when authenticated user has none', function () {
    Storage::fake('public');

    [$user, $token] = userWithSanctumToken();

    $response = $this->withToken($token)->post(SHOPS_BASE_URI, [
        'name' => 'Nouvelle Boutique',
        'description' => 'Description test.',
        'company_name' => 'SARL Test',
        'vat_number' => 'RC-CON-2024-B-9999',
        'logo' => UploadedFile::fake()->image('logo.png', 100, 100),
    ], [
        'Accept' => 'application/json',
    ]);

    $response->assertCreated()
        ->assertJsonPath('shop.name', 'Nouvelle Boutique')
        ->assertJsonPath('shop.slug', 'nouvelle-boutique');

    $this->assertDatabaseHas('shops', [
        'user_id' => $user->id,
        'name' => 'Nouvelle Boutique',
        'slug' => 'nouvelle-boutique',
    ]);

    $path = Shop::query()->where('user_id', $user->id)->value('logo_url');
    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

it('creates a shop without logo when logo is omitted', function () {
    [$user, $token] = userWithSanctumToken();

    $this->withToken($token)->post(SHOPS_BASE_URI, [
        'name' => 'Boutique Sans Logo',
        'description' => 'Sans fichier.',
        'company_name' => 'SARL',
        'vat_number' => 'RC-1',
    ], [
        'Accept' => 'application/json',
    ])
        ->assertCreated()
        ->assertJsonPath('shop.logo_url', null);

    $this->assertDatabaseHas('shops', [
        'user_id' => $user->id,
        'name' => 'Boutique Sans Logo',
        'logo_url' => null,
    ]);
});

it('returns 401 when creating a shop without authentication', function () {
    $this->postJson(SHOPS_BASE_URI, [
        'name' => 'Test',
        'description' => 'D',
        'company_name' => 'C',
        'vat_number' => 'V',
    ])
        ->assertUnauthorized();
});

it('returns 403 when user already owns a shop', function () {
    [$user, $token] = userWithSanctumToken();
    Shop::factory()->create(['user_id' => $user->id]);

    $this->withToken($token)->post(SHOPS_BASE_URI, [
        'name' => 'Deuxième tentative',
        'description' => 'Impossible.',
        'company_name' => 'SARL',
        'vat_number' => 'RC-2',
    ], [
        'Accept' => 'application/json',
    ])
        ->assertForbidden();
});

it('returns 422 when name is missing on create', function () {
    [, $token] = userWithSanctumToken();

    $this->withToken($token)->postJson(SHOPS_BASE_URI, [
        'description' => 'Seulement description',
        'company_name' => 'SARL',
        'vat_number' => 'RC-3',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('returns 422 when slug is not unique on create', function () {
    Shop::factory()->create(['slug' => 'taken-slug']);

    [, $token] = userWithSanctumToken();

    $this->withToken($token)->postJson(SHOPS_BASE_URI, [
        'name' => 'Autre nom',
        'slug' => 'taken-slug',
        'description' => 'D',
        'company_name' => 'C',
        'vat_number' => 'RC-4',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);
});

it('allows owner to update their shop', function () {
    Storage::fake('public');

    [$user, $token] = userWithSanctumToken();
    $shop = Shop::factory()->create([
        'user_id' => $user->id,
        'name' => 'Ancien nom',
        'slug' => 'ancien-nom',
    ]);

    $this->withToken($token)->put(SHOPS_BASE_URI.'/'.$shop->id, [
        'name' => 'Nom mis à jour',
        'slug' => 'nom-mis-a-jour',
        'description' => 'Nouvelle description.',
        'company_name' => 'SARL MAJ',
        'vat_number' => 'RC-MAJ',
    ], [
        'Accept' => 'application/json',
    ])
        ->assertSuccessful()
        ->assertJsonPath('shop.name', 'Nom mis à jour')
        ->assertJsonPath('shop.slug', 'nom-mis-a-jour');

    $this->assertDatabaseHas('shops', [
        'id' => $shop->id,
        'name' => 'Nom mis à jour',
        'slug' => 'nom-mis-a-jour',
    ]);
});

it('allows owner to replace logo on update', function () {
    Storage::fake('public');

    [$user, $token] = userWithSanctumToken();
    $shop = Shop::factory()->create([
        'user_id' => $user->id,
    ]);

    $this->withToken($token)->put(SHOPS_BASE_URI.'/'.$shop->id, [
        'name' => $shop->name,
        'slug' => $shop->slug,
        'logo' => UploadedFile::fake()->image('new-logo.jpg', 80, 80),
    ], [
        'Accept' => 'application/json',
    ])
        ->assertSuccessful();

    $path = $shop->fresh()?->getRawOriginal('logo_url');
    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

it('returns 403 when another user tries to update the shop', function () {
    $owner = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $owner->id]);

    [, $otherToken] = userWithSanctumToken();

    $this->withToken($otherToken)->putJson(SHOPS_BASE_URI.'/'.$shop->id, [
        'name' => 'Hack',
        'slug' => 'hack-slug',
    ])
        ->assertForbidden();
});

it('returns 401 when updating a shop without authentication', function () {
    $shop = Shop::factory()->create();

    $this->putJson(SHOPS_BASE_URI.'/'.$shop->id, [
        'name' => 'Sans auth',
        'slug' => 'sans-auth',
    ])
        ->assertUnauthorized();
});

it('allows owner to soft delete their shop', function () {
    [$user, $token] = userWithSanctumToken();
    $shop = Shop::factory()->create([
        'user_id' => $user->id,
        'slug' => 'boutique-a-supprimer',
    ]);

    $this->withToken($token)->deleteJson(SHOPS_BASE_URI.'/'.$shop->id, [], [
        'Accept' => 'application/json',
    ])
        ->assertNoContent();

    $this->assertSoftDeleted($shop);
});

it('removes logo from disk when owner soft deletes shop', function () {
    Storage::fake('public');

    [$user, $token] = userWithSanctumToken();
    $path = 'shops/logos/fake-logo.png';
    Storage::disk('public')->put($path, 'binary-fake-image');
    $shop = Shop::factory()->create([
        'user_id' => $user->id,
        'logo_url' => $path,
    ]);

    $this->withToken($token)->deleteJson(SHOPS_BASE_URI.'/'.$shop->id, [], [
        'Accept' => 'application/json',
    ])
        ->assertNoContent();

    Storage::disk('public')->assertMissing($path);
    $this->assertSoftDeleted($shop);
});

it('allows creating a new shop after soft deleting the previous one', function () {
    Storage::fake('public');

    [$user, $token] = userWithSanctumToken();
    $old = Shop::factory()->create([
        'user_id' => $user->id,
        'name' => 'Première',
        'slug' => 'premiere-boutique',
    ]);

    $this->withToken($token)->deleteJson(SHOPS_BASE_URI.'/'.$old->id, [], [
        'Accept' => 'application/json',
    ])
        ->assertNoContent();

    $this->withToken($token)->post(SHOPS_BASE_URI, [
        'name' => 'Deuxième boutique',
        'description' => 'Après suppression logique.',
        'company_name' => 'SARL 2',
        'vat_number' => 'RC-2',
    ], [
        'Accept' => 'application/json',
    ])
        ->assertCreated()
        ->assertJsonPath('shop.name', 'Deuxième boutique')
        ->assertJsonPath('shop.slug', 'deuxieme-boutique');
});

it('allows reusing slug after previous shop with same slug was soft deleted', function () {
    [$user, $token] = userWithSanctumToken();
    $first = Shop::factory()->create([
        'user_id' => $user->id,
        'slug' => 'slug-reutilisable',
    ]);

    $this->withToken($token)->deleteJson(SHOPS_BASE_URI.'/'.$first->id, [], [
        'Accept' => 'application/json',
    ])
        ->assertNoContent();

    $this->withToken($token)->postJson(SHOPS_BASE_URI, [
        'name' => 'Nouvelle avec même slug',
        'slug' => 'slug-reutilisable',
        'description' => 'D',
        'company_name' => 'C',
        'vat_number' => 'RC-R',
    ])
        ->assertCreated()
        ->assertJsonPath('shop.slug', 'slug-reutilisable');
});

it('returns 403 when another user tries to delete the shop', function () {
    $owner = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $owner->id]);

    [, $otherToken] = userWithSanctumToken();

    $this->withToken($otherToken)->deleteJson(SHOPS_BASE_URI.'/'.$shop->id)
        ->assertForbidden();
});

it('returns 401 when deleting a shop without authentication', function () {
    $shop = Shop::factory()->create();

    $this->deleteJson(SHOPS_BASE_URI.'/'.$shop->id)
        ->assertUnauthorized();
});

it('returns 404 when showing a soft deleted shop by slug', function () {
    $shop = Shop::factory()->create(['slug' => 'gone-shop']);
    $shop->delete();

    $this->getJson(SHOPS_BASE_URI.'/gone-shop', [
        'Accept' => 'application/json',
    ])
        ->assertNotFound();
});

it('returns 404 when updating a soft deleted shop by id', function () {
    [$user, $token] = userWithSanctumToken();
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $shopId = $shop->id;
    $shop->delete();

    $this->withToken($token)->putJson(SHOPS_BASE_URI.'/'.$shopId, [
        'name' => 'Trop tard',
        'slug' => 'trop-tard',
    ])
        ->assertNotFound();
});

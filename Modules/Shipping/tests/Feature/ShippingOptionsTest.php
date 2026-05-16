<?php

declare(strict_types=1);

use Modules\Core\Enums\UserRole;
use Modules\Core\Models\User;

it('returns conservative FLASH ETA when neighborhood slug is omitted', function (): void {
    $user = User::factory()->create(['role' => UserRole::Customer]);
    $this->actingAs($user, 'sanctum');

    $response = $this->getJson(SHIPPING_OPTIONS_URL)
        ->assertSuccessful()
        ->assertJsonPath('neighborhood_warning', 'Quartier non fourni : délais conservateurs appliqués pour FLASH.');

    $flash = collect($response->json('options'))->firstWhere('code', 'FLASH');
    expect($flash['eta_min_minutes'])->toBe(150)
        ->and($flash['eta_max_minutes'])->toBe(240);
});

it('returns unknown neighborhood warning and Zone B FLASH ETA for invalid slug', function (): void {
    $user = User::factory()->create(['role' => UserRole::Customer]);
    $this->actingAs($user, 'sanctum');

    $response = $this->getJson(SHIPPING_OPTIONS_URL.'?neighborhood_slug=quartier-inexistant')
        ->assertSuccessful()
        ->assertJsonPath('neighborhood_warning', 'Quartier inconnu : délais conservateurs (Zone B) utilisés pour FLASH.');

    $flash = collect($response->json('options'))->firstWhere('code', 'FLASH');
    expect($flash['eta_min_minutes'])->toBe(150)
        ->and($flash['eta_max_minutes'])->toBe(240);
});

it('requires authentication for shipping and shipment verification endpoints', function (): void {
    $this->getJson(SHIPPING_OPTIONS_URL)->assertUnauthorized();

    $this->postJson(SHIPMENTS_VERIFY_PICKUP_URL, [
        'shipment_id' => 1,
        'exit_code' => 'AAAAAA',
    ])->assertUnauthorized();

    $this->postJson(SHIPMENTS_VERIFY_DELIVERY_URL, [
        'shipment_id' => 1,
        'confirmation_code' => 'BBBBBB',
    ])->assertUnauthorized();
});

it('returns validation error when shipment_id is invalid for verify endpoints', function (): void {
    $user = User::factory()->create(['role' => UserRole::Customer]);
    $this->actingAs($user, 'sanctum');

    $this->postJson(SHIPMENTS_VERIFY_PICKUP_URL, [
        'shipment_id' => 999999,
        'exit_code' => 'AAAAAA',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['shipment_id']);

    $this->postJson(SHIPMENTS_VERIFY_DELIVERY_URL, [
        'shipment_id' => 999999,
        'confirmation_code' => 'BBBBBB',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['shipment_id']);
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Modules\Core\Models\Customer;
use Modules\Core\Models\User;

const REGISTER_URI = '/api/v1/auth/register';

const LOGIN_URI = '/api/v1/auth/login';

const ME_URI = '/api/v1/auth/me';

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'email' => 'newuser@example.com',
        'password' => 'ValidPass1!',
        'password_confirmation' => 'ValidPass1!',
        'first_name' => 'Amina',
        'last_name' => 'Diallo',
        'phone' => '+221771234567',
    ], $overrides);
}

it('should register a user and persist user and customer records', function () {
    $payload = validRegistrationPayload();

    $response = $this->postJson(REGISTER_URI, $payload);

    $response->assertCreated()
        ->assertJsonStructure([
            'token',
            'user' => [
                'id',
                'name',
                'email',
                'role',
                'admin_level',
                'is_active',
                'customer',
                'created_at',
                'updated_at',
            ],
        ]);

    $this->assertDatabaseHas('users', [
        'email' => $payload['email'],
    ]);

    $this->assertDatabaseHas('customers', [
        'first_name' => $payload['first_name'],
        'last_name' => $payload['last_name'],
        'phone' => $payload['phone'],
    ]);
});

it('should fail registration when email is missing', function () {
    $payload = validRegistrationPayload();
    unset($payload['email']);

    $this->postJson(REGISTER_URI, $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('should fail registration when password is too short', function () {
    $this->postJson(REGISTER_URI, validRegistrationPayload([
        'password' => 'short',
        'password_confirmation' => 'short',
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('should fail registration when password_confirmation does not match', function () {
    $this->postJson(REGISTER_URI, validRegistrationPayload([
        'password_confirmation' => 'DifferentPass1!',
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password_confirmation']);
});

it('should fail registration when email is already taken', function () {
    User::factory()->create([
        'email' => 'taken@example.com',
    ]);

    $this->postJson(REGISTER_URI, validRegistrationPayload([
        'email' => 'taken@example.com',
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('should login and return a sanctum token', function () {
    $user = User::factory()->create([
        'email' => 'login@example.com',
        'password' => Hash::make('ValidPass1!'),
    ]);

    $this->postJson(LOGIN_URI, [
        'email' => $user->email,
        'password' => 'ValidPass1!',
    ])
        ->assertSuccessful()
        ->assertJsonStructure([
            'token',
            'user' => [
                'id',
                'name',
                'email',
                'role',
                'admin_level',
                'is_active',
            ],
        ]);
});

it('should fail login with invalid credentials', function () {
    User::factory()->create([
        'email' => 'wrong@example.com',
        'password' => Hash::make('CorrectPass1!'),
    ]);

    $this->postJson(LOGIN_URI, [
        'email' => 'wrong@example.com',
        'password' => 'WrongPass1!',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('should allow authenticated user to fetch profile', function () {
    $user = User::factory()->create([
        'password' => Hash::make('ValidPass1!'),
    ]);

    Customer::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Test',
        'last_name' => 'Profile',
        'phone' => '+221771111111',
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $this->getJson(ME_URI, [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ])
        ->assertSuccessful()
        ->assertJsonStructure([
            'user' => [
                'id',
                'name',
                'email',
                'role',
                'admin_level',
                'is_active',
                'customer',
                'created_at',
                'updated_at',
            ],
        ]);
});

it('should block unauthenticated access to profile', function () {
    $this->getJson(ME_URI, ['Accept' => 'application/json'])
        ->assertUnauthorized();
});

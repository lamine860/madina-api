<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Core\Dto\AuthTokenResponse;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;

final class AuthService
{
    /**
     * @param  array{email: string, password: string, first_name: string, last_name: string, phone: string}  $data
     */
    public function register(array $data): AuthTokenResponse
    {
        return DB::transaction(function () use ($data): AuthTokenResponse {
            $fullName = $data['first_name'] . ' ' . $data['last_name'];

            $user = User::query()->create([
                'name' => $fullName,
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => UserRole::Customer,
                'admin_level' => 0,
                'is_active' => true,
            ]);

            $user->customer()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'],
            ]);

            event(new Registered($user));

            $token = $user->createToken('auth')->plainTextToken;

            return new AuthTokenResponse($user->load('customer'), $token);
        });
    }

    /**
     * @param  array{email: string, password: string}  $credentials
     */
    public function login(array $credentials): AuthTokenResponse
    {
        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => [__('Your account is inactive.')],
            ]);
        }

        $token = $user->createToken('auth')->plainTextToken;

        return new AuthTokenResponse($user->load('customer'), $token);
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}

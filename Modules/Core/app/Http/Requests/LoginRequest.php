<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation pour la connexion par e-mail et mot de passe.
 *
 * @bodyParam email string required Adresse e-mail du compte. Example: client@example.com
 * @bodyParam password string required Mot de passe. Example: Str0ng!Pass
 */
final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array{description?: string, example?: string}>
     */
    public function bodyParameters(): array
    {
        return [
            'email' => [
                'description' => 'Adresse e-mail du compte.',
                'example' => 'client@example.com',
            ],
            'password' => [
                'description' => 'Mot de passe du compte.',
                'example' => 'Str0ng!Pass',
            ],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}

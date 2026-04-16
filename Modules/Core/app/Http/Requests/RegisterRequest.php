<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validation pour l'inscription d'un nouveau compte client.
 *
 * @bodyParam email string required Adresse e-mail unique. Example: client@example.com
 * @bodyParam password string required Mot de passe (politique `Password::defaults()`).
 * @bodyParam password_confirmation string required Doit correspondre exactement au champ `password`.
 * @bodyParam first_name string required Prénom. Example: Amina
 * @bodyParam last_name string required Nom de famille. Example: Diallo
 * @bodyParam phone string required Numéro de téléphone. Example: +221771234567
 */
final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Métadonnées Scribe pour les paramètres du corps (descriptions / exemples).
     *
     * @return array<string, array{description?: string, example?: string}>
     */
    public function bodyParameters(): array
    {
        return [
            'email' => [
                'description' => 'Adresse e-mail unique du compte.',
                'example' => 'client@example.com',
            ],
            'password' => [
                'description' => 'Mot de passe (respecte les règles Laravel Password::defaults()).',
                'example' => 'Str0ng!Pass',
            ],
            'password_confirmation' => [
                'description' => 'Confirmation du mot de passe : doit être identique à `password`.',
                'example' => 'Str0ng!Pass',
            ],
            'first_name' => [
                'description' => 'Prénom du client.',
                'example' => 'Amina',
            ],
            'last_name' => [
                'description' => 'Nom de famille du client.',
                'example' => 'Diallo',
            ],
            'phone' => [
                'description' => 'Numéro de téléphone de contact.',
                'example' => '+221771234567',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'password_confirmation' => ['required', 'string', 'same:password'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
        ];
    }
}

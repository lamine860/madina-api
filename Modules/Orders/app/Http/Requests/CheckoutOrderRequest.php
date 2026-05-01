<?php

declare(strict_types=1);

namespace Modules\Orders\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class CheckoutOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Métadonnées Scribe pour le corps (adresses + notes).
     *
     * @return array<string, array{description?: string, example?: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'shipping_address.line1' => [
                'description' => 'Ligne d’adresse (rue, numéro).',
                'example' => '12 avenue de la République',
            ],
            'shipping_address.city' => [
                'description' => 'Ville de livraison.',
                'example' => 'Conakry',
            ],
            'shipping_address.postal_code' => [
                'description' => 'Code postal ou équivalent.',
                'example' => 'BP 1234',
            ],
            'shipping_address.country' => [
                'description' => 'Code pays ISO 3166-1 alpha-2.',
                'example' => 'GN',
            ],
            'billing_address' => [
                'description' => 'Adresse de facturation (optionnelle). Même structure que shipping_address si fournie.',
                'example' => [
                    'line1' => '12 avenue de la République',
                    'city' => 'Conakry',
                    'postal_code' => 'BP 1234',
                    'country' => 'GN',
                ],
            ],
            'notes' => [
                'description' => 'Message libre pour le vendeur (optionnel).',
                'example' => 'Livrer avant 18h si possible.',
            ],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'shipping_address' => ['required', 'array'],
            'shipping_address.line1' => ['required', 'string', 'max:255'],
            'shipping_address.city' => ['required', 'string', 'max:120'],
            'shipping_address.postal_code' => ['required', 'string', 'max:32'],
            'shipping_address.country' => ['required', 'string', 'size:2'],
            'billing_address' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Shipping\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class VerifyPickupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Métadonnées Scribe pour la vérification du retrait.
     *
     * @return array<string, array{description?: string, example?: int|string}>
     */
    public function bodyParameters(): array
    {
        return [
            'shipment_id' => [
                'description' => 'Identifiant de l’expédition.',
                'example' => 1,
            ],
            'exit_code' => [
                'description' => 'Code sortie (obligatoire pour Kilora, non utilisé en auto-livraison boutique).',
                'example' => 'ABC123',
            ],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'shipment_id' => ['required', 'integer', 'exists:shipments,id'],
            'exit_code' => ['nullable', 'string', 'max:32'],
        ];
    }
}

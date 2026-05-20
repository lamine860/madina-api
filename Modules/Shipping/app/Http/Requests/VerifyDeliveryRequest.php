<?php

declare(strict_types=1);

namespace Modules\Shipping\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class VerifyDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Métadonnées Scribe pour la vérification de livraison.
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
            'confirmation_code' => [
                'description' => 'Code de confirmation remis au client.',
                'example' => 'XYZ789',
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
            'confirmation_code' => ['required', 'string', 'max:32'],
        ];
    }
}

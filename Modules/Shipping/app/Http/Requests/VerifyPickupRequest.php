<?php

declare(strict_types=1);

namespace Modules\Shipping\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam shipment_id integer required Identifiant de l’expédition. Example: 1
 * @bodyParam exit_code string Code sortie (obligatoire pour Kilora, non utilisé en auto-livraison boutique). Example: ABC123
 */
final class VerifyPickupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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

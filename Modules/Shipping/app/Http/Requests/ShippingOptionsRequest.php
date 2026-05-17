<?php

declare(strict_types=1);

namespace Modules\Shipping\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @queryParam neighborhood_slug string Slug du quartier pour déterminer la zone et les ETA. Quartiers seedés — Zone A : `madina`, `dixinn`, `matam`, `ratoma-centre` ; Zone B : `koloma`. Example: madina
 */
final class ShippingOptionsRequest extends FormRequest
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
            'neighborhood_slug' => ['nullable', 'string', 'max:120'],
        ];
    }
}

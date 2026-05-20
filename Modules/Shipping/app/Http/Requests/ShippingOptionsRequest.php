<?php

declare(strict_types=1);

namespace Modules\Shipping\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ShippingOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Métadonnées Scribe pour les paramètres de requête.
     *
     * @return array<string, array{description?: string, example?: string}>
     */
    public function queryParameters(): array
    {
        return [
            'neighborhood_slug' => [
                'description' => 'Slug du quartier pour déterminer la zone et les ETA. Quartiers seedés — Zone A : madina, dixinn, matam, ratoma-centre ; Zone B : koloma.',
                'example' => 'madina',
            ],
        ];
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

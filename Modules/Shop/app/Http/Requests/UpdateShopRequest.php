<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Métadonnées Scribe pour les paramètres du corps (descriptions / exemples).
     *
     * @return array<string, array{description?: string, example?: string}>
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Le nom public de la boutique.',
                'example' => 'Madina Tech Store',
            ],
            'description' => [
                'description' => 'Une brève description de ce que propose la boutique.',
                'example' => 'Vente de matériel informatique et accessoires.',
            ],
            'company_name' => [
                'description' => 'Le nom légal de l\'entreprise.',
                'example' => 'Madina SARL',
            ],
            'vat_number' => [
                'description' => 'Le numéro NINEA ou Registre du commerce.',
                'example' => 'RC-CON-2024-B-1234',
            ],
            'logo' => [
                'description' => 'Le fichier image du logo (Max 2Mo).',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('shops', 'slug')->ignore($this->route('shop')?->id),
            ],
            'description' => ['nullable', 'string'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'logo' => [
                'nullable',
                'file',
                'max:2048',
                'mimes:jpeg,jpg,png,svg',
            ],
        ];
    }
}

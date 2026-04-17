<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class CreateShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        // return $this->user() !== null;
        return true;
    }


    /**
     * Métadonnées Scribe pour les paramètres du corps (descriptions / exemples).
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('shops', 'slug'),
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

    protected function prepareForValidation(): void
    {
        if ($this->filled('name') && ! $this->filled('slug')) {
            $this->merge([
                'slug' => Str::slug($this->string('name')->toString()),
            ]);
        }
    }
}

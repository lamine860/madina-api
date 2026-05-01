<?php

declare(strict_types=1);

namespace Modules\Cart\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Models\ProductVariant;

final class StoreCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Métadonnées Scribe pour l’ajout au panier.
     *
     * @return array<string, array{description?: string, example?: int}>
     */
    public function bodyParameters(): array
    {
        return [
            'product_variant_id' => [
                'description' => 'Identifiant de la variante produit à ajouter au panier.',
                'example' => 1,
            ],
            'quantity' => [
                'description' => 'Quantité souhaitée (≥ 1).',
                'example' => 2,
            ],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'product_variant_id' => ['required', 'integer', Rule::exists(ProductVariant::class, 'id')],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}

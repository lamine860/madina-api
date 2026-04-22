<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use JsonException;
use Modules\Shop\Entities\Shop;

final class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Métadonnées Scribe : corps produit + variantes + galerie (fichiers).
     *
     * @return array<string, array{description?: string, example?: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Nom commercial du produit.',
                'example' => 'T-shirt coton bio',
            ],
            'slug' => [
                'description' => 'Identifiant URL unique dans la boutique (optionnel, dérivé du nom si absent).',
                'example' => 't-shirt-coton-bio',
            ],
            'description' => [
                'description' => 'Description longue (HTML ou texte).',
                'example' => 'T-shirt 100% coton certifié, coupe droite.',
            ],
            'base_price' => [
                'description' => 'Prix catalogue de référence (décimal).',
                'example' => 29.99,
            ],
            'is_active' => [
                'description' => 'Produit visible dans le catalogue.',
                'example' => true,
            ],
            'category_id' => [
                'description' => 'ID de la catégorie (arbre hiérarchique via parent_id).',
                'example' => 1,
            ],
            'variants' => [
                'description' => 'Liste des variantes (SKU, prix, stock, attributs JSON). En multipart, peut être envoyée en JSON string.',
                'example' => [
                    [
                        'sku' => 'TS-BIO-M-BLK',
                        'price' => 29.99,
                        'stock_qty' => 42,
                        'attributes' => [
                            'size' => 'M',
                            'color' => 'black',
                        ],
                    ],
                ],
            ],
            'variants.*.sku' => [
                'description' => 'Code SKU unique globalement.',
                'example' => 'TS-BIO-M-BLK',
            ],
            'variants.*.price' => [
                'description' => 'Prix de la variante.',
                'example' => 29.99,
            ],
            'variants.*.stock_qty' => [
                'description' => 'Quantité disponible.',
                'example' => 42,
            ],
            'variants.*.attributes' => [
                'description' => 'Attributs de variante sous forme d’objet JSON (taille, couleur, etc.).',
                'example' => ['size' => 'M', 'color' => 'black'],
            ],
            'gallery' => [
                'description' => 'Fichiers image (galerie). Clé répétée gallery[] en multipart.',
                'example' => ['(binary)', '(binary)'],
            ],
            'gallery.*' => [
                'description' => 'Une image (jpeg, png, webp, gif, svg).',
                'example' => '(binary)',
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
                Rule::unique('products', 'slug')->where(function ($query) {
                    $query->whereNull('deleted_at');
                    $shop = $this->route('shop');
                    if ($shop instanceof Shop) {
                        $query->where('shop_id', $shop->id);
                    }
                }),
            ],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.sku' => [
                'required',
                'string',
                'max:100',
                'distinct',
                Rule::unique('product_variants', 'sku')->where(static function ($query): void {
                    $query->whereNull('deleted_at');
                }),
            ],
            'variants.*.price' => ['required', 'numeric', 'min:0'],
            'variants.*.stock_qty' => ['required', 'integer', 'min:0'],
            'variants.*.attributes' => ['required', 'array'],
            'gallery' => ['sometimes', 'array', 'max:20'],
            'gallery.*' => ['file', 'max:5120', 'mimes:jpeg,jpg,png,webp,gif,svg'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $variants = $this->input('variants');
        if (is_string($variants)) {
            try {
                $decoded = json_decode($variants, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $this->merge(['variants' => $decoded]);
                }
            } catch (JsonException) {
                $this->merge(['variants' => []]);
            }
        }

        if ($this->filled('name') && ! $this->filled('slug')) {
            $this->merge([
                'slug' => Str::slug($this->string('name')->toString()),
            ]);
        }
    }
}

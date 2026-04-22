<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use JsonException;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;

final class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Scribe : le stock n’est pas modifiable via cette route (lecture seule / ajustements via stock dédié).
     *
     * @return array<string, array{description?: string, example?: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Nom commercial du produit. Si modifié, le slug peut être régénéré seulement si le produit est inactif ou créé depuis au moins 24h (protection SEO des fiches actives récentes).',
                'example' => 'T-shirt coton bio',
            ],
            'description' => [
                'description' => 'Description longue.',
                'example' => 'T-shirt 100% coton.',
            ],
            'category_id' => [
                'description' => 'ID de catégorie.',
                'example' => 1,
            ],
            'base_price' => [
                'description' => 'Prix catalogue de référence.',
                'example' => 29.99,
            ],
            'is_active' => [
                'description' => 'Produit visible ou non.',
                'example' => true,
            ],
            'variants' => [
                'description' => 'Variantes à synchroniser. `stock_qty` est ignoré ici (non envoyé) — utiliser l’API d’ajustement de stock.',
                'example' => [
                    [
                        'id' => 1,
                        'sku' => 'TS-BIO-M-BLK',
                        'price' => 29.99,
                        'attributes' => ['size' => 'M', 'color' => 'black'],
                    ],
                ],
            ],
            'variants.*.id' => [
                'description' => 'ID de variante existante (optionnel ; sinon identification par SKU).',
                'example' => 1,
            ],
            'variants.*.sku' => [
                'description' => 'SKU unique (hors lignes concurrentes de la même requête).',
                'example' => 'TS-BIO-M-BLK',
            ],
            'variants.*.price' => [
                'description' => 'Prix de la variante.',
                'example' => 29.99,
            ],
            'variants.*.attributes' => [
                'description' => 'Attributs JSON (clés normalisées en minuscules côté serveur).',
                'example' => ['size' => 'M'],
            ],
            'variants.*.stock_qty' => [
                'description' => 'INTERDIT sur cette route — le stock ne peut pas être modifié ici (risque d’écraser les mouvements réels). Utiliser `adjustStock` / endpoints stock.',
                'example' => null,
            ],
            'gallery' => [
                'description' => 'Images supplémentaires à ajouter à la galerie (multipart, optionnel). Pour réordonner ou supprimer, utiliser les routes `PATCH/DELETE .../images`.',
                'example' => ['(binary)'],
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
        $product = $this->route('product');
        $productId = $product instanceof Product ? $product->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.id' => [
                'nullable',
                'integer',
                Rule::exists('product_variants', 'id')->where(
                    fn ($query) => $productId !== null
                        ? $query->where('product_id', $productId)
                        : $query
                ),
            ],
            'variants.*.sku' => ['required', 'string', 'max:100'],
            'variants.*.price' => ['required', 'numeric', 'min:0'],
            'variants.*.attributes' => ['required', 'array'],
            'variants.*.stock_qty' => ['prohibited'],
            'gallery' => ['sometimes', 'array', 'max:20'],
            'gallery.*' => ['file', 'max:5120', 'mimes:jpeg,jpg,png,webp,gif,svg'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $product = $this->route('product');
            if (! $product instanceof Product) {
                return;
            }

            $variants = $this->input('variants', []);
            if (! is_array($variants)) {
                return;
            }

            $skusSeen = [];
            foreach ($variants as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $sku = $row['sku'] ?? null;
                if (! is_string($sku) || $sku === '') {
                    continue;
                }

                if (isset($skusSeen[$sku])) {
                    $validator->errors()->add(
                        "variants.{$index}.sku",
                        'Le SKU est dupliqué dans la requête.'
                    );
                }
                $skusSeen[$sku] = true;

                $query = ProductVariant::query()->where('sku', $sku);
                if (! empty($row['id'])) {
                    $query->where('id', '!=', (int) $row['id']);
                }
                if ($query->exists()) {
                    $validator->errors()->add(
                        "variants.{$index}.sku",
                        'Ce SKU est déjà utilisé par une autre variante.'
                    );
                }
            }
        });
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
    }
}

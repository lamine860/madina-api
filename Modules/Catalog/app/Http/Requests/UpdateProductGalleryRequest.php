<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Catalog\Models\Product;

final class UpdateProductGalleryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array{description?: string, example?: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'image_ids' => [
                'description' => 'Liste ordonnée des IDs `product_images` du produit (permutation complète de la galerie actuelle). Vide si le produit n’a aucune image.',
                'example' => [3, 1, 2],
            ],
            'featured_image_id' => [
                'description' => 'ID de l’image mise en avant (doit figurer dans `image_ids`). Si absent, la première entrée de `image_ids` devient la mise en avant.',
                'example' => 1,
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
            'image_ids' => ['required', 'array'],
            'image_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('product_images', 'id')->where(
                    fn ($query) => $productId !== null
                        ? $query->where('product_id', $productId)
                        : $query
                ),
            ],
            'featured_image_id' => [
                'nullable',
                'integer',
                Rule::exists('product_images', 'id')->where(
                    fn ($query) => $productId !== null
                        ? $query->where('product_id', $productId)
                        : $query
                ),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $product = $this->route('product');
            if (! $product instanceof Product) {
                return;
            }

            $imageIds = $this->input('image_ids', []);
            if (! is_array($imageIds)) {
                return;
            }

            $dbIds = $product->productImages()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $requestIds = collect($imageIds)->map(fn ($id) => (int) $id)->sort()->values()->all();

            if ($dbIds !== $requestIds) {
                $validator->errors()->add(
                    'image_ids',
                    'La liste doit contenir exactement les images du produit, sans doublon ni omission.',
                );
            }

            $featuredId = $this->input('featured_image_id');
            if ($featuredId === null || $featuredId === '') {
                return;
            }

            $featuredInt = (int) $featuredId;
            $ordered = collect($imageIds)->map(fn ($id) => (int) $id)->all();
            if (! in_array($featuredInt, $ordered, true)) {
                $validator->errors()->add(
                    'featured_image_id',
                    'L’image mise en avant doit faire partie de la liste ordonnée.',
                );
            }
        });
    }
}

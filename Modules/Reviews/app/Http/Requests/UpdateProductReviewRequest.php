<?php

declare(strict_types=1);

namespace Modules\Reviews\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateProductReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Métadonnées Scribe pour la mise à jour d’un avis produit.
     *
     * @return array<string, array{description?: string, example?: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'rating' => [
                'description' => 'Nouvelle note de 1 à 5 étoiles.',
                'example' => 4,
            ],
            'comment' => [
                'description' => 'Commentaire mis à jour (max. '.config('reviews.comment_max_length', 2000).' caractères).',
                'example' => 'Très satisfait après utilisation.',
            ],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'rating' => ['sometimes', 'required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:'.config('reviews.comment_max_length', 2000)],
        ];
    }
}

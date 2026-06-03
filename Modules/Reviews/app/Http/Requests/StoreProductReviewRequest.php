<?php

declare(strict_types=1);

namespace Modules\Reviews\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreProductReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Métadonnées Scribe pour la création d’un avis produit.
     *
     * @return array<string, array{description?: string, example?: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'rating' => [
                'description' => 'Note de 1 à 5 étoiles.',
                'example' => 5,
            ],
            'comment' => [
                'description' => 'Commentaire optionnel (max. '.config('reviews.comment_max_length', 2000).' caractères).',
                'example' => 'Produit conforme à la description, livraison rapide.',
            ],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:'.config('reviews.comment_max_length', 2000)],
        ];
    }
}

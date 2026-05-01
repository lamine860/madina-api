<?php

declare(strict_types=1);

namespace Modules\Cart\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Métadonnées Scribe pour la mise à jour d’une ligne panier.
     *
     * @return array<string, array{description?: string, example?: int}>
     */
    public function bodyParameters(): array
    {
        return [
            'quantity' => [
                'description' => 'Nouvelle quantité pour la ligne (≥ 1).',
                'example' => 3,
            ],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreProductImagesRequest extends FormRequest
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
            'gallery' => [
                'description' => 'Fichiers image à ajouter (multipart, clé répétée gallery[]).',
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
            'gallery' => ['required', 'array', 'min:1', 'max:20'],
            'gallery.*' => ['file', 'max:5120', 'mimes:jpeg,jpg,png,webp,gif,svg'],
        ];
    }
}

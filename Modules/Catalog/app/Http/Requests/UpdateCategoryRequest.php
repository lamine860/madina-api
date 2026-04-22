<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Services\CategoryService;

final class UpdateCategoryRequest extends FormRequest
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
            'name' => [
                'description' => 'Nouveau libellé.',
                'example' => 'Électronique grand public',
            ],
            'slug' => [
                'description' => 'Slug URL (optionnel ; sinon dérivé automatiquement du nom).',
                'example' => 'electronique-grand-public',
            ],
            'parent_id' => [
                'description' => 'Nouvelle catégorie parente (null = racine). Ne peut être la catégorie elle-même ni un de ses descendants.',
                'example' => 2,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('category');
        $categoryId = $category instanceof Category ? $category->id : 0;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('categories', 'slug')->ignore($categoryId),
            ],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('slug') && $this->input('slug') === '') {
            $this->merge(['slug' => null]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $category = $this->route('category');
            $parentId = $this->input('parent_id');

            if (! $category instanceof Category) {
                return;
            }

            if ($parentId !== null && $parentId !== '' && (int) $parentId === $category->id) {
                $validator->errors()->add(
                    'parent_id',
                    'Une catégorie ne peut pas être son propre parent.',
                );
            }

            if ($parentId === null || $parentId === '') {
                return;
            }

            $parentId = (int) $parentId;

            /** @var CategoryService $service */
            $service = app(CategoryService::class);
            $descendantIds = $service->descendantIds($category);

            if (in_array($parentId, $descendantIds, true)) {
                $validator->errors()->add(
                    'parent_id',
                    'Le parent ne peut pas être l’une des sous-catégories de cette catégorie.',
                );
            }
        });
    }
}

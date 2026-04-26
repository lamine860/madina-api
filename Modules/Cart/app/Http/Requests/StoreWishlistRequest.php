<?php

declare(strict_types=1);

namespace Modules\Cart\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Models\ProductVariant;

final class StoreWishlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'product_variant_id' => ['required', 'integer', Rule::exists(ProductVariant::class, 'id')],
        ];
    }
}

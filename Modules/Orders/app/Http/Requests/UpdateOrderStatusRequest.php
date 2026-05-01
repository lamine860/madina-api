<?php

declare(strict_types=1);

namespace Modules\Orders\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Orders\Enums\OrderStatus;

final class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Métadonnées Scribe pour le corps (statut de commande).
     *
     * @return array<string, array{description?: string, example?: string}>
     */
    public function bodyParameters(): array
    {
        return [
            'status' => [
                'description' => 'Nouveau statut : pending, paid, processing, shipped, cancelled, refunded.',
                'example' => 'paid',
            ],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(OrderStatus::class)],
        ];
    }
}

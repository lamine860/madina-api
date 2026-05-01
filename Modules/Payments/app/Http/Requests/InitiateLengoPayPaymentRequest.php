<?php

declare(strict_types=1);

namespace Modules\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Payments\Enums\PaymentMethod;

final class InitiateLengoPayPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Métadonnées Scribe pour l’initiation LengoPay.
     *
     * @return array<string, array{description?: string, example?: string}>
     */
    public function bodyParameters(): array
    {
        return [
            'payment_method' => [
                'description' => 'Canal de paiement : orange, moov ou card.',
                'example' => 'orange',
            ],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'string', Rule::enum(PaymentMethod::class)],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class InitiateOrangeMoneyPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Métadonnées Scribe pour l’initiation Orange Money.
     *
     * @return array<string, array{description?: string, example?: string}>
     */
    public function bodyParameters(): array
    {
        return [
            'customer_msisdn' => [
                'description' => 'Numéro Orange Money du client au format international Guinée (224 + 9 chiffres).',
                'example' => '224621234567',
            ],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'customer_msisdn' => ['nullable', 'string', 'regex:/^224\d{9}$/'],
        ];
    }
}

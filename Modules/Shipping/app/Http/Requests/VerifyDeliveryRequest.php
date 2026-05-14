<?php

declare(strict_types=1);

namespace Modules\Shipping\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class VerifyDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'shipment_id' => ['required', 'integer', 'exists:shipments,id'],
            'confirmation_code' => ['required', 'string', 'max:32'],
        ];
    }
}

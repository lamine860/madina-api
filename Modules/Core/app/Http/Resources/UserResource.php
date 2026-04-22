<?php

declare(strict_types=1);

namespace Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Models\User;

/**
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'admin_level' => $this->admin_level,
            'is_active' => $this->is_active,
            'customer' => $this->when(
                $this->relationLoaded('customer') && $this->customer !== null,
                function (): array {
                    $customer = $this->customer;

                    return [
                        'id' => $customer->id,
                        'first_name' => $customer->first_name,
                        'last_name' => $customer->last_name,
                        'phone' => $customer->phone,
                        'addresses' => $customer->relationLoaded('addresses')
                            ? AddressResource::collection($customer->addresses)->resolve()
                            : [],
                    ];
                }
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

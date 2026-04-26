<?php

declare(strict_types=1);

namespace Modules\Orders\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Http\Resources\ProductVariantResource;
use Modules\Orders\Models\OrderItem;

/**
 * @mixin OrderItem
 */
final class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shop_id' => $this->shop_id,
            'quantity' => $this->quantity,
            'unit_price' => (string) $this->unit_price,
            'subtotal' => (string) $this->subtotal,
            'variant' => $this->when(
                $this->relationLoaded('productVariant') && $this->productVariant !== null,
                fn () => new ProductVariantResource($this->productVariant),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

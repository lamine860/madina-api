<?php

declare(strict_types=1);

namespace Modules\Cart\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Cart\Models\CartItem;
use Modules\Catalog\Http\Resources\ProductVariantResource;

/**
 * @mixin CartItem
 */
final class CartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'variant' => $this->when(
                $this->relationLoaded('productVariant') && $this->productVariant !== null,
                fn () => new ProductVariantResource($this->productVariant),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

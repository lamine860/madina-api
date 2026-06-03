<?php

declare(strict_types=1);

namespace Modules\Reviews\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Orders\Models\OrderItem;

/**
 * @mixin OrderItem
 */
final class ReviewableOrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $variant = $this->productVariant;
        $product = $variant?->product;

        return [
            'id' => $this->id,
            'shop_id' => $this->shop_id,
            'quantity' => $this->quantity,
            'product' => $product !== null ? [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
            ] : null,
            'variant' => $variant !== null ? [
                'id' => $variant->id,
                'sku' => $variant->sku,
            ] : null,
        ];
    }
}

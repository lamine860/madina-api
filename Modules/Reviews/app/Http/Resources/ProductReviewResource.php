<?php

declare(strict_types=1);

namespace Modules\Reviews\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reviews\Models\ProductReview;

/**
 * @mixin ProductReview
 */
final class ProductReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'reviewer' => [
                'display_name' => 'Client vérifié',
            ],
            'product_id' => $this->product_id,
            'order_item_id' => $this->order_item_id,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

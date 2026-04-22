<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Entities\ProductImage;

/**
 * @mixin ProductImage
 */
final class ProductImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'urls' => [
                'original' => $this->originalPublicUrl(),
                'thumbnail' => $this->thumbnailPublicUrl(),
                'large' => $this->largePublicUrl(),
            ],
            'url' => $this->largePublicUrl(),
            'is_featured' => $this->is_featured,
            'sort_order' => $this->sort_order,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'product_id',
    'filename',
    'is_featured',
    'sort_order',
])]
class ProductImage extends Model
{
    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function relativePathOriginal(): string
    {
        return $this->variantBasePath().'/original/'.$this->filename;
    }

    public function relativePathThumbnail(): string
    {
        return $this->variantBasePath().'/thumbs/'.$this->filename;
    }

    public function relativePathLarge(): string
    {
        return $this->variantBasePath().'/large/'.$this->filename;
    }

    public function originalPublicUrl(): string
    {
        return Storage::disk('public')->url($this->relativePathOriginal());
    }

    public function thumbnailPublicUrl(): string
    {
        return Storage::disk('public')->url($this->relativePathThumbnail());
    }

    public function largePublicUrl(): string
    {
        return Storage::disk('public')->url($this->relativePathLarge());
    }

    /**
     * Supprime les trois variantes WebP sur le disque public.
     */
    public function deletePhysicalVariants(): void
    {
        $disk = Storage::disk('public');

        foreach ([
            $this->relativePathOriginal(),
            $this->relativePathThumbnail(),
            $this->relativePathLarge(),
        ] as $relative) {
            if ($relative !== '' && $disk->exists($relative)) {
                $disk->delete($relative);
            }
        }
    }

    private function variantBasePath(): string
    {
        return 'products/'.$this->product_id;
    }

    protected static function booted(): void
    {
        static::deleting(function (ProductImage $image): void {
            $image->deletePhysicalVariants();
        });
    }
}

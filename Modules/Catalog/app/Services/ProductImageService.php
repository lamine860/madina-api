<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductImage;
use Throwable;

final class ProductImageService
{
    /**
     * @param  list<int>  $orderedImageIds
     */
    public function syncGalleryOrderAndFeatured(Product $product, array $orderedImageIds, ?int $featuredImageId): void
    {
        try {
            DB::transaction(function () use ($product, $orderedImageIds, $featuredImageId): void {
                Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

                $featuredId = $featuredImageId;
                if ($featuredId === null && $orderedImageIds !== []) {
                    $featuredId = (int) $orderedImageIds[0];
                }

                foreach (array_values($orderedImageIds) as $index => $id) {
                    ProductImage::query()
                        ->where('product_id', $product->id)
                        ->whereKey((int) $id)
                        ->update([
                            'sort_order' => $index,
                            'is_featured' => $featuredId !== null && (int) $id === $featuredId,
                        ]);
                }
            });
        } catch (Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function deleteImage(Product $product, ProductImage $image): void
    {
        try {
            DB::transaction(function () use ($product, $image): void {
                Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

                /** @var ProductImage|null $locked */
                $locked = ProductImage::query()
                    ->where('product_id', $product->id)
                    ->whereKey($image->id)
                    ->lockForUpdate()
                    ->first();

                if ($locked === null) {
                    throw (new ModelNotFoundException)->setModel(ProductImage::class, [$image->id]);
                }

                $wasFeatured = $locked->is_featured;
                $locked->delete();

                $remaining = ProductImage::query()
                    ->where('product_id', $product->id)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get();

                foreach ($remaining as $position => $row) {
                    $payload = ['sort_order' => $position];
                    if ($wasFeatured) {
                        $payload['is_featured'] = $position === 0;
                    }
                    $row->update($payload);
                }
            });
        } catch (Throwable $e) {
            report($e);
            throw $e;
        }
    }
}

<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Entities\ProductImage;
use Modules\Catalog\Http\Requests\StoreProductImagesRequest;
use Modules\Catalog\Http\Requests\UpdateProductGalleryRequest;
use Modules\Catalog\Http\Resources\ProductResource;
use Modules\Catalog\Services\ProductImageService;
use Modules\Catalog\Services\ProductService;
use Modules\Shop\Entities\Shop;

final class ProductImageController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ProductService $productService,
        private readonly ProductImageService $productImageService,
    ) {}

    /**
     * @group Catalogue
     *
     * @subgroup Produits — galerie
     *
     * @authenticated
     */
    public function store(StoreProductImagesRequest $request, Shop $shop, Product $product): JsonResponse
    {
        $this->assertProductBelongsToShop($shop, $product);
        $this->authorize('update', $product);

        $gallery = $request->file('gallery');
        $files = is_array($gallery) ? array_values($gallery) : ($gallery !== null ? [$gallery] : []);

        $this->productService->uploadImages($product, $files);

        $product->load(['variants', 'category', 'shop', 'productImages']);

        return response()->json([
            'product' => new ProductResource($product),
        ], 201);
    }

    /**
     * @group Catalogue
     *
     * @subgroup Produits — galerie
     *
     * @authenticated
     */
    public function update(UpdateProductGalleryRequest $request, Shop $shop, Product $product): JsonResponse
    {
        $this->assertProductBelongsToShop($shop, $product);
        $this->authorize('update', $product);

        /** @var list<int> $ids */
        $ids = array_map(static fn (mixed $id): int => (int) $id, $request->validated('image_ids'));
        $featured = $request->validated('featured_image_id');

        $this->productImageService->syncGalleryOrderAndFeatured(
            $product,
            $ids,
            $featured !== null ? (int) $featured : null,
        );

        $product->load(['variants', 'category', 'shop', 'productImages']);

        return response()->json([
            'product' => new ProductResource($product),
        ]);
    }

    /**
     * @group Catalogue
     *
     * @subgroup Produits — galerie
     *
     * @authenticated
     */
    public function destroy(Shop $shop, Product $product, ProductImage $product_image): JsonResponse
    {
        $this->assertProductBelongsToShop($shop, $product);
        $this->authorize('update', $product);

        abort_unless($product_image->product_id === $product->id, 404);

        $this->productImageService->deleteImage($product, $product_image);

        $product->load(['variants', 'category', 'shop', 'productImages']);

        return response()->json([
            'product' => new ProductResource($product),
        ]);
    }

    private function assertProductBelongsToShop(Shop $shop, Product $product): void
    {
        abort_unless($product->shop_id === $shop->id, 404);
    }
}

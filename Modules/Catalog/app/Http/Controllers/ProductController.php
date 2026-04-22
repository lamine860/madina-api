<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Catalog\Http\Requests\StoreProductRequest;
use Modules\Catalog\Http\Requests\UpdateProductRequest;
use Modules\Catalog\Http\Resources\ProductResource;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Services\ProductService;
use Modules\Shop\Models\Shop;

final class ProductController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ProductService $productService,
    ) {}

    /**
     * @group Catalogue
     *
     * @subgroup Produits
     */
    public function index(Request $request, Shop $shop): JsonResponse
    {
        $products = Product::query()
            ->where('shop_id', $shop->id)
            ->where('is_active', true)
            ->with(['category', 'variants', 'productImages'])
            ->latest('id')
            ->paginate(perPage: 20);

        return ProductResource::collection($products)->response();
    }

    /**
     * @group Catalogue
     *
     * @subgroup Produits
     */
    public function show(Shop $shop, Product $product): JsonResponse
    {
        abort_unless($product->shop_id === $shop->id, 404);
        abort_unless($product->is_active, 404);

        $product->load(['category', 'variants', 'productImages']);

        return response()->json([
            'product' => new ProductResource($product),
        ]);
    }

    /**
     * @group Catalogue
     *
     * @subgroup Produits
     *
     * @authenticated
     */
    public function store(StoreProductRequest $request, Shop $shop): JsonResponse
    {
        $this->authorize('manageProducts', $shop);

        $validated = $request->validated();
        $variants = $validated['variants'];
        unset($validated['variants']);

        $productPayload = $validated;
        $gallery = $request->file('gallery');
        $galleryFiles = $gallery !== null ? (is_array($gallery) ? array_values($gallery) : [$gallery]) : null;

        $product = $this->productService->createProductWithVariants(
            $shop,
            $productPayload,
            $variants,
            $galleryFiles,
        );

        return response()->json([
            'product' => new ProductResource($product),
        ], 201);
    }

    /**
     * @group Catalogue
     *
     * @subgroup Produits
     *
     * @authenticated
     */
    public function update(UpdateProductRequest $request, Shop $shop, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $validated = $request->validated();
        $variants = $validated['variants'];
        unset($validated['variants']);

        $gallery = $request->file('gallery');
        $galleryFiles = $gallery !== null ? (is_array($gallery) ? array_values($gallery) : [$gallery]) : null;

        $updated = $this->productService->updateProduct($product, $validated, $variants, $galleryFiles);

        return response()->json([
            'product' => new ProductResource($updated),
        ]);
    }

    /**
     * @group Catalogue
     *
     * @subgroup Produits
     *
     * @authenticated
     *
     * @queryParam force boolean Suppression définitive (hard delete) : fichiers galerie sur disque + enregistrements. Défaut : suppression logique. Example: false
     */
    public function destroy(Request $request, Shop $shop, Product $product): JsonResponse
    {
        abort_unless($product->shop_id === $shop->id, 404);

        $this->authorize('delete', $product);

        $this->productService->deleteProduct($product, $request->boolean('force'));

        return response()->json(null, 204);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Reviews\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Catalog\Models\Product;
use Modules\Reviews\Http\Resources\ProductReviewResource;
use Modules\Reviews\Services\ReviewService;
use Modules\Shop\Models\Shop;

final class ProductReviewController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviewService,
    ) {}

    /**
     * Liste paginée des avis publiés d’un produit.
     *
     * @group Avis
     *
     * @subgroup Produits
     */
    public function index(Request $request, Shop $shop, Product $product): JsonResponse
    {
        abort_unless($product->shop_id === $shop->id, 404);
        abort_unless($product->is_active, 404);

        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);
        $reviews = $this->reviewService->paginateForProduct($product, $perPage);
        $summary = $this->reviewService->summaryForProduct($product);

        return response()->json([
            'data' => ProductReviewResource::collection($reviews->items())->resolve(),
            'summary' => $summary,
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }
}

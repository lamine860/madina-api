<?php

declare(strict_types=1);

namespace Modules\Reviews\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderItem;
use Modules\Reviews\Http\Requests\StoreProductReviewRequest;
use Modules\Reviews\Http\Resources\ProductReviewResource;
use Modules\Reviews\Http\Resources\ReviewableOrderItemResource;
use Modules\Reviews\Services\ReviewService;

final class OrderReviewController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ReviewService $reviewService,
    ) {}

    /**
     * Articles livrés de la commande encore sans avis.
     *
     * @group Avis
     *
     * @subgroup Commandes
     *
     * @authenticated
     */
    public function reviewableItems(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $items = $this->reviewService->reviewableItemsForOrder($order);

        return response()->json([
            'items' => ReviewableOrderItemResource::collection(collect($items))->resolve(),
        ]);
    }

    /**
     * Crée un avis pour un article de commande livré.
     *
     * @group Avis
     *
     * @subgroup Commandes
     *
     * @authenticated
     */
    public function store(StoreProductReviewRequest $request, Order $order, OrderItem $orderItem): JsonResponse
    {
        $this->authorize('view', $order);

        abort_unless((int) $orderItem->order_id === (int) $order->id, 404);

        $validated = $request->validated();

        $review = $this->reviewService->createReview(
            $request->user(),
            $orderItem,
            (int) $validated['rating'],
            $validated['comment'] ?? null,
        );

        return response()->json([
            'review' => new ProductReviewResource($review),
        ], 201);
    }
}

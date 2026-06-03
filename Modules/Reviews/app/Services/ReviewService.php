<?php

declare(strict_types=1);

namespace Modules\Reviews\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Models\Product;
use Modules\Core\Models\User;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderItem;
use Modules\Reviews\Models\ProductReview;
use Modules\Shipping\Enums\ShipmentStatus;
use Modules\Shipping\Models\Shipment;

final class ReviewService
{
    public function __construct(
        private readonly ReviewEligibilityService $eligibilityService,
    ) {}

    public function createReview(User $user, OrderItem $orderItem, int $rating, ?string $comment): ProductReview
    {
        $this->eligibilityService->assertCanReview($user, $orderItem);

        $orderItem->loadMissing('productVariant.product');

        $variant = $orderItem->productVariant;
        $product = $variant?->product;

        if ($variant === null || $product === null) {
            throw ValidationException::withMessages([
                'order_item_id' => ['Produit introuvable pour cet article.'],
            ]);
        }

        return DB::transaction(function () use ($user, $orderItem, $rating, $comment, $variant, $product): ProductReview {
            $now = now();

            return ProductReview::query()->create([
                'user_id' => $user->id,
                'order_id' => $orderItem->order_id,
                'order_item_id' => $orderItem->id,
                'shop_id' => $orderItem->shop_id,
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'rating' => $rating,
                'comment' => $comment,
                'is_published' => true,
                'published_at' => $now,
            ]);
        });
    }

    public function updateReview(ProductReview $review, int $rating, ?string $comment): ProductReview
    {
        $review->update([
            'rating' => $rating,
            'comment' => $comment,
        ]);

        return $review->fresh() ?? $review;
    }

    public function deleteReview(ProductReview $review): void
    {
        $review->delete();
    }

    /**
     * @return LengthAwarePaginator<int, ProductReview>
     */
    public function paginateForProduct(Product $product, int $perPage = 20): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 50);

        return ProductReview::query()
            ->where('product_id', $product->id)
            ->where('is_published', true)
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * @return array{average_rating: ?string, reviews_count: int}
     */
    public function summaryForProduct(Product $product): array
    {
        $aggregate = ProductReview::query()
            ->where('product_id', $product->id)
            ->where('is_published', true)
            ->selectRaw('AVG(rating) as average_rating, COUNT(*) as reviews_count')
            ->first();

        $count = (int) ($aggregate->reviews_count ?? 0);
        $average = $aggregate->average_rating !== null
            ? number_format((float) $aggregate->average_rating, 2, '.', '')
            : null;

        return [
            'average_rating' => $count > 0 ? $average : null,
            'reviews_count' => $count,
        ];
    }

    /**
     * @return list<OrderItem>
     */
    public function reviewableItemsForOrder(Order $order): array
    {
        $order->loadMissing('items.productVariant.product');

        $deliveredShopIds = Shipment::query()
            ->where('order_id', $order->id)
            ->where('status', ShipmentStatus::Delivered)
            ->pluck('shop_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($deliveredShopIds === []) {
            return [];
        }

        $reviewedOrderItemIds = ProductReview::query()
            ->where('order_id', $order->id)
            ->pluck('order_item_id')
            ->all();

        return $order->items
            ->filter(static function (OrderItem $item) use ($deliveredShopIds, $reviewedOrderItemIds): bool {
                if (! in_array((int) $item->shop_id, $deliveredShopIds, true)) {
                    return false;
                }

                return ! in_array((int) $item->id, $reviewedOrderItemIds, true);
            })
            ->values()
            ->all();
    }
}

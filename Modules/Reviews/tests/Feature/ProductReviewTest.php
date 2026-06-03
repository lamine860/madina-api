<?php

declare(strict_types=1);

use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Core\Enums\UserRole;
use Modules\Core\Models\User;
use Modules\Orders\Events\OrderPaid;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderItem;
use Modules\Reviews\Models\ProductReview;
use Modules\Reviews\Services\ReviewService;
use Modules\Shipping\Models\Shipment;
use Modules\Shop\Models\Shop;

const REVIEWS_BASE = '/api/v1';

/**
 * @param  array{shop: Shop, variant: ProductVariant, seller: User}|null  $setup
 * @return array{order: Order, orderItem: OrderItem, setup: array{shop: Shop, variant: ProductVariant, seller: User}, buyer: User}
 */
function createDeliveredOrderForReview(?array $setup = null): array
{
    $setup ??= createSellerShopVariant('25.00');
    $buyer = User::factory()->create(['role' => UserRole::Customer]);
    $order = createPaidOrderFromSetups([
        ['shop' => $setup['shop'], 'variant' => $setup['variant'], 'subtotal' => '25.00'],
    ]);
    $order->update(['user_id' => $buyer->id]);

    OrderPaid::dispatch($order->fresh());

    $shipment = Shipment::query()->where('order_id', $order->id)->sole();

    test()->actingAs($setup['seller'], 'sanctum')
        ->postJson(SHIPMENTS_VERIFY_PICKUP_URL, [
            'shipment_id' => $shipment->id,
            'exit_code' => (string) $shipment->exit_code,
        ])->assertSuccessful();

    test()->actingAs($setup['seller'], 'sanctum')
        ->postJson(SHIPMENTS_VERIFY_DELIVERY_URL, [
            'shipment_id' => $shipment->id,
            'confirmation_code' => (string) $shipment->confirmation_code,
        ])->assertSuccessful();

    $orderItem = OrderItem::query()->where('order_id', $order->id)->sole();

    return [
        'order' => $order->fresh(),
        'orderItem' => $orderItem,
        'setup' => $setup,
        'buyer' => $buyer,
    ];
}

it('lists published reviews publicly and excludes unpublished ones', function (): void {
    $context = createDeliveredOrderForReview();
    $product = Product::query()->findOrFail($context['setup']['variant']->product_id);
    $shop = $context['setup']['shop'];

    ProductReview::query()->create([
        'user_id' => $context['buyer']->id,
        'order_id' => $context['order']->id,
        'order_item_id' => $context['orderItem']->id,
        'shop_id' => $shop->id,
        'product_id' => $product->id,
        'product_variant_id' => $context['setup']['variant']->id,
        'rating' => 5,
        'comment' => 'Excellent produit',
        'is_published' => true,
        'published_at' => now(),
    ]);

    $otherBuyer = User::factory()->create(['role' => UserRole::Customer]);
    $otherOrder = createPaidOrderFromSetups([
        ['shop' => $context['setup']['shop'], 'variant' => $context['setup']['variant'], 'subtotal' => '25.00'],
    ]);
    $otherOrder->update(['user_id' => $otherBuyer->id]);
    $otherItem = OrderItem::query()->where('order_id', $otherOrder->id)->sole();

    ProductReview::query()->create([
        'user_id' => $otherBuyer->id,
        'order_id' => $otherOrder->id,
        'order_item_id' => $otherItem->id,
        'shop_id' => $shop->id,
        'product_id' => $product->id,
        'product_variant_id' => $context['setup']['variant']->id,
        'rating' => 1,
        'comment' => 'Masqué',
        'is_published' => false,
        'published_at' => null,
    ]);

    $this->getJson(REVIEWS_BASE.'/shops/'.$shop->slug.'/products/'.$product->slug.'/reviews')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.rating', 5)
        ->assertJsonPath('data.0.reviewer.display_name', 'Client vérifié')
        ->assertJsonPath('summary.reviews_count', 1)
        ->assertJsonPath('summary.average_rating', '5.00');
});

it('creates a review after delivery', function (): void {
    $context = createDeliveredOrderForReview();

    $this->actingAs($context['buyer'], 'sanctum')
        ->postJson(REVIEWS_BASE.'/orders/'.$context['order']->id.'/items/'.$context['orderItem']->id.'/reviews', [
            'rating' => 4,
            'comment' => 'Très bien',
        ])
        ->assertCreated()
        ->assertJsonPath('review.rating', 4)
        ->assertJsonPath('review.comment', 'Très bien');

    expect(ProductReview::query()->count())->toBe(1);
});

it('rejects review when shipment is not delivered', function (): void {
    $setup = createSellerShopVariant('10.00');
    $buyer = User::factory()->create(['role' => UserRole::Customer]);
    $order = createPaidOrderFromSetups([
        ['shop' => $setup['shop'], 'variant' => $setup['variant'], 'subtotal' => '10.00'],
    ]);
    $order->update(['user_id' => $buyer->id]);
    OrderPaid::dispatch($order->fresh());

    $orderItem = OrderItem::query()->where('order_id', $order->id)->sole();

    $this->actingAs($buyer, 'sanctum')
        ->postJson(REVIEWS_BASE.'/orders/'.$order->id.'/items/'.$orderItem->id.'/reviews', [
            'rating' => 5,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.order_item_id.0', 'Ce produit n’a pas encore été livré.');
});

it('forbids another user from creating a review on a foreign order', function (): void {
    $context = createDeliveredOrderForReview();
    $intruder = User::factory()->create(['role' => UserRole::Customer]);

    $this->actingAs($intruder, 'sanctum')
        ->postJson(REVIEWS_BASE.'/orders/'.$context['order']->id.'/items/'.$context['orderItem']->id.'/reviews', [
            'rating' => 5,
        ])
        ->assertForbidden();
});

it('rejects duplicate review for the same order item', function (): void {
    $context = createDeliveredOrderForReview();

    $this->actingAs($context['buyer'], 'sanctum')
        ->postJson(REVIEWS_BASE.'/orders/'.$context['order']->id.'/items/'.$context['orderItem']->id.'/reviews', [
            'rating' => 5,
            'comment' => 'Premier avis',
        ])
        ->assertCreated();

    $this->actingAs($context['buyer'], 'sanctum')
        ->postJson(REVIEWS_BASE.'/orders/'.$context['order']->id.'/items/'.$context['orderItem']->id.'/reviews', [
            'rating' => 3,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.order_item_id.0', 'Un avis existe déjà pour cet article.');
});

it('allows owner to update and delete their review', function (): void {
    $context = createDeliveredOrderForReview();

    $create = $this->actingAs($context['buyer'], 'sanctum')
        ->postJson(REVIEWS_BASE.'/orders/'.$context['order']->id.'/items/'.$context['orderItem']->id.'/reviews', [
            'rating' => 3,
            'comment' => 'Correct',
        ])
        ->assertCreated();

    $reviewId = (int) $create->json('review.id');

    $this->actingAs($context['buyer'], 'sanctum')
        ->patchJson(REVIEWS_BASE.'/reviews/'.$reviewId, [
            'rating' => 5,
            'comment' => 'Finalement excellent',
        ])
        ->assertSuccessful()
        ->assertJsonPath('review.rating', 5);

    $this->actingAs($context['buyer'], 'sanctum')
        ->deleteJson(REVIEWS_BASE.'/reviews/'.$reviewId)
        ->assertNoContent();

    expect(ProductReview::query()->count())->toBe(0);
});

it('forbids another user from updating a review', function (): void {
    $context = createDeliveredOrderForReview();

    $create = $this->actingAs($context['buyer'], 'sanctum')
        ->postJson(REVIEWS_BASE.'/orders/'.$context['order']->id.'/items/'.$context['orderItem']->id.'/reviews', [
            'rating' => 4,
        ])
        ->assertCreated();

    $reviewId = (int) $create->json('review.id');
    $intruder = User::factory()->create(['role' => UserRole::Customer]);

    $this->actingAs($intruder, 'sanctum')
        ->patchJson(REVIEWS_BASE.'/reviews/'.$reviewId, ['rating' => 1])
        ->assertForbidden();
});

it('returns only delivered order items without reviews', function (): void {
    $context = createDeliveredOrderForReview();

    $this->actingAs($context['buyer'], 'sanctum')
        ->getJson(REVIEWS_BASE.'/orders/'.$context['order']->id.'/reviewable-items')
        ->assertSuccessful()
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.id', $context['orderItem']->id);

    $this->actingAs($context['buyer'], 'sanctum')
        ->postJson(REVIEWS_BASE.'/orders/'.$context['order']->id.'/items/'.$context['orderItem']->id.'/reviews', [
            'rating' => 5,
        ])
        ->assertCreated();

    $this->actingAs($context['buyer'], 'sanctum')
        ->getJson(REVIEWS_BASE.'/orders/'.$context['order']->id.'/reviewable-items')
        ->assertSuccessful()
        ->assertJsonCount(0, 'items');
});

it('includes review summary on product show', function (): void {
    $context = createDeliveredOrderForReview();
    $product = Product::query()->findOrFail($context['setup']['variant']->product_id);
    $shop = $context['setup']['shop'];

    $this->actingAs($context['buyer'], 'sanctum')
        ->postJson(REVIEWS_BASE.'/orders/'.$context['order']->id.'/items/'.$context['orderItem']->id.'/reviews', [
            'rating' => 4,
            'comment' => 'Bon produit',
        ])
        ->assertCreated();

    $otherContext = createDeliveredOrderForReview($context['setup']);
    $otherItem = OrderItem::query()
        ->where('order_id', $otherContext['order']->id)
        ->sole();

    $this->actingAs($otherContext['buyer'], 'sanctum')
        ->postJson(REVIEWS_BASE.'/orders/'.$otherContext['order']->id.'/items/'.$otherItem->id.'/reviews', [
            'rating' => 2,
        ])
        ->assertCreated();

    $this->getJson(REVIEWS_BASE.'/shops/'.$shop->slug.'/products/'.$product->slug)
        ->assertSuccessful()
        ->assertJsonPath('review_summary.reviews_count', 2)
        ->assertJsonPath('review_summary.average_rating', '3.00');
});

it('computes correct summary after two published reviews', function (): void {
    $context = createDeliveredOrderForReview();
    $product = Product::query()->findOrFail($context['setup']['variant']->product_id);

    $this->actingAs($context['buyer'], 'sanctum')
        ->postJson(REVIEWS_BASE.'/orders/'.$context['order']->id.'/items/'.$context['orderItem']->id.'/reviews', [
            'rating' => 5,
        ])
        ->assertCreated();

    $otherContext = createDeliveredOrderForReview($context['setup']);
    $otherItem = OrderItem::query()
        ->where('order_id', $otherContext['order']->id)
        ->sole();

    $this->actingAs($otherContext['buyer'], 'sanctum')
        ->postJson(REVIEWS_BASE.'/orders/'.$otherContext['order']->id.'/items/'.$otherItem->id.'/reviews', [
            'rating' => 3,
        ])
        ->assertCreated();

    $summary = app(ReviewService::class)->summaryForProduct($product);

    expect($summary['reviews_count'])->toBe(2)
        ->and($summary['average_rating'])->toBe('4.00');
});

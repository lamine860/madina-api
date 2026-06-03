<?php

declare(strict_types=1);

use Modules\Core\Enums\UserRole;
use Modules\Core\Models\User;
use Modules\Orders\Events\OrderPaid;
use Modules\Orders\Models\OrderItem;
use Modules\Reviews\Models\ProductReview;
use Modules\Reviews\Services\ReviewEligibilityService;
use Modules\Shipping\Enums\ShipmentStatus;
use Modules\Shipping\Models\Shipment;

it('returns false when order is not owned by user', function (): void {
    $setup = createSellerShopVariant('10.00');
    $buyer = User::factory()->create(['role' => UserRole::Customer]);
    $other = User::factory()->create(['role' => UserRole::Customer]);
    $order = createPaidOrderFromSetups([
        ['shop' => $setup['shop'], 'variant' => $setup['variant'], 'subtotal' => '10.00'],
    ]);
    $order->update(['user_id' => $buyer->id]);
    OrderPaid::dispatch($order->fresh());

    $shipment = Shipment::query()->where('order_id', $order->id)->sole();
    $shipment->update(['status' => ShipmentStatus::Delivered, 'delivery_verified_at' => now()]);

    $orderItem = OrderItem::query()->where('order_id', $order->id)->sole();
    $service = app(ReviewEligibilityService::class);

    expect($service->canReview($other, $orderItem))->toBeFalse();
});

it('returns false when shipment is not delivered', function (): void {
    $setup = createSellerShopVariant('10.00');
    $buyer = User::factory()->create(['role' => UserRole::Customer]);
    $order = createPaidOrderFromSetups([
        ['shop' => $setup['shop'], 'variant' => $setup['variant'], 'subtotal' => '10.00'],
    ]);
    $order->update(['user_id' => $buyer->id]);
    OrderPaid::dispatch($order->fresh());

    $orderItem = OrderItem::query()->where('order_id', $order->id)->sole();
    $service = app(ReviewEligibilityService::class);

    expect($service->canReview($buyer, $orderItem))->toBeFalse();
});

it('returns false when review already exists', function (): void {
    $setup = createSellerShopVariant('10.00');
    $buyer = User::factory()->create(['role' => UserRole::Customer]);
    $order = createPaidOrderFromSetups([
        ['shop' => $setup['shop'], 'variant' => $setup['variant'], 'subtotal' => '10.00'],
    ]);
    $order->update(['user_id' => $buyer->id]);
    OrderPaid::dispatch($order->fresh());

    $shipment = Shipment::query()->where('order_id', $order->id)->sole();
    $shipment->update(['status' => ShipmentStatus::Delivered, 'delivery_verified_at' => now()]);

    $orderItem = OrderItem::query()->where('order_id', $order->id)->sole();
    $variant = $setup['variant'];
    $product = $variant->product;

    ProductReview::query()->create([
        'user_id' => $buyer->id,
        'order_id' => $order->id,
        'order_item_id' => $orderItem->id,
        'shop_id' => $setup['shop']->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'rating' => 5,
        'comment' => 'Déjà noté',
        'is_published' => true,
        'published_at' => now(),
    ]);

    $service = app(ReviewEligibilityService::class);

    expect($service->canReview($buyer, $orderItem))->toBeFalse();
});

it('returns true when order item is delivered and not yet reviewed', function (): void {
    $setup = createSellerShopVariant('10.00');
    $buyer = User::factory()->create(['role' => UserRole::Customer]);
    $order = createPaidOrderFromSetups([
        ['shop' => $setup['shop'], 'variant' => $setup['variant'], 'subtotal' => '10.00'],
    ]);
    $order->update(['user_id' => $buyer->id]);
    OrderPaid::dispatch($order->fresh());

    $shipment = Shipment::query()->where('order_id', $order->id)->sole();
    $shipment->update(['status' => ShipmentStatus::Delivered, 'delivery_verified_at' => now()]);

    $orderItem = OrderItem::query()->where('order_id', $order->id)->sole();
    $service = app(ReviewEligibilityService::class);

    expect($service->canReview($buyer, $orderItem))->toBeTrue();
});

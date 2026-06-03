<?php

declare(strict_types=1);

namespace Modules\Reviews\Services;

use Illuminate\Validation\ValidationException;
use Modules\Core\Models\User;
use Modules\Orders\Models\OrderItem;
use Modules\Reviews\Models\ProductReview;
use Modules\Shipping\Enums\ShipmentStatus;
use Modules\Shipping\Models\Shipment;

final class ReviewEligibilityService
{
    public function canReview(User $user, OrderItem $orderItem): bool
    {
        try {
            $this->assertCanReview($user, $orderItem);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    /**
     * @throws ValidationException
     */
    public function assertCanReview(User $user, OrderItem $orderItem): void
    {
        $orderItem->loadMissing('order');

        if ((int) $orderItem->order->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'order_item_id' => ['Vous ne pouvez noter que vos propres achats.'],
            ]);
        }

        $isDelivered = Shipment::query()
            ->where('order_id', $orderItem->order_id)
            ->where('shop_id', $orderItem->shop_id)
            ->where('status', ShipmentStatus::Delivered)
            ->exists();

        if (! $isDelivered) {
            throw ValidationException::withMessages([
                'order_item_id' => ['Ce produit n’a pas encore été livré.'],
            ]);
        }

        if (ProductReview::query()->where('order_item_id', $orderItem->id)->exists()) {
            throw ValidationException::withMessages([
                'order_item_id' => ['Un avis existe déjà pour cet article.'],
            ]);
        }
    }
}

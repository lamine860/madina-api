<?php

declare(strict_types=1);

namespace Modules\Shipping\Policies;

use Modules\Core\Enums\UserRole;
use Modules\Core\Models\User;
use Modules\Orders\Models\Order;
use Modules\Shipping\Models\Shipment;

final class ShipmentPolicy
{
    public function view(User $user, Shipment $shipment): bool
    {
        return $this->canAccessOrder($user, $shipment->order);
    }

    public function verifyPickup(User $user, Shipment $shipment): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role !== UserRole::Seller) {
            return false;
        }

        $shop = $user->shop;

        return $shop !== null && (int) $shipment->shop_id === (int) $shop->id;
    }

    public function verifyDelivery(User $user, Shipment $shipment): bool
    {
        return $this->verifyPickup($user, $shipment);
    }

    private function canAccessOrder(User $user, Order $order): bool
    {
        if ((int) $order->user_id === (int) $user->id) {
            return true;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        $shop = $user->shop;

        if ($shop === null) {
            return false;
        }

        return $order->items()->where('shop_id', $shop->id)->exists();
    }
}

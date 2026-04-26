<?php

declare(strict_types=1);

namespace Modules\Orders\Policies;

use Modules\Core\Enums\UserRole;
use Modules\Core\Models\User;
use Modules\Orders\Models\Order;

final class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
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

    public function updateStatus(User $user, Order $order): bool
    {
        return $user->role === UserRole::Admin;
    }
}

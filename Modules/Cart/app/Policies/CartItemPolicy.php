<?php

declare(strict_types=1);

namespace Modules\Cart\Policies;

use Modules\Cart\Models\CartItem;
use Modules\Core\Models\User;

final class CartItemPolicy
{
    public function update(User $user, CartItem $cartItem): bool
    {
        return $user->id === $cartItem->user_id;
    }

    public function delete(User $user, CartItem $cartItem): bool
    {
        return $user->id === $cartItem->user_id;
    }
}

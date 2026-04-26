<?php

declare(strict_types=1);

namespace Modules\Cart\Policies;

use Modules\Cart\Models\Wishlist;
use Modules\Core\Models\User;

final class WishlistPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, Wishlist $wishlist): bool
    {
        return $user->id === $wishlist->user_id;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Catalog\Policies;

use Modules\Catalog\Entities\Product;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;

final class ProductPolicy
{
    /**
     * Mise à jour réservée au propriétaire de la boutique du produit.
     */
    public function update(User $user, Product $product): bool
    {
        $product->loadMissing('shop');

        return (int) $product->shop->user_id === (int) $user->id;
    }

    /**
     * Suppression : propriétaire de la boutique ou administrateur.
     */
    public function delete(User $user, Product $product): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        $product->loadMissing('shop');

        return (int) $product->shop->user_id === (int) $user->id;
    }
}

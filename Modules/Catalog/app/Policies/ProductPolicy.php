<?php

declare(strict_types=1);

namespace Modules\Catalog\Policies;

use Modules\Catalog\Entities\Product;
use Modules\Core\Entities\User;

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
}

<?php

declare(strict_types=1);

namespace Modules\Shop\Policies;

use Modules\Core\Entities\User;
use Modules\Shop\Entities\Shop;

final class ShopPolicy
{
    /**
     * Un utilisateur ne peut créer une boutique que s'il n'en possède pas déjà une.
     */
    public function create(User $user): bool
    {
        return ! Shop::query()->where('user_id', $user->id)->exists();
    }

    /**
     * Seul le propriétaire peut modifier sa boutique.
     */
    public function update(User $user, Shop $shop): bool
    {
        return $shop->user_id === $user->id;
    }

    /**
     * Gestion du catalogue produits (création / édition) réservée au propriétaire de la boutique.
     */
    public function manageProducts(User $user, Shop $shop): bool
    {
        return $shop->user_id === $user->id;
    }
}

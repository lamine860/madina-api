<?php

declare(strict_types=1);

namespace Modules\Shop\Policies;

use Modules\Core\Models\User;
use Modules\Shop\Models\Shop;

final class ShopPolicy
{
    /**
     * Un utilisateur ne peut créer une boutique que s'il n'en possède pas déjà une active
     * (les boutiques soft-supprimées ne comptent pas grâce au scope global du modèle).
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
     * Seul le propriétaire peut supprimer (soft) sa boutique.
     */
    public function delete(User $user, Shop $shop): bool
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

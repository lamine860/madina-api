<?php

declare(strict_types=1);

namespace Modules\Cart\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Cart\Models\Wishlist;
use Modules\Catalog\Models\ProductVariant;
use Modules\Core\Models\User;
use Throwable;

final class WishlistService
{
    /**
     * @return Collection<int, Wishlist>
     */
    public function listForUser(User $user): Collection
    {
        return Wishlist::query()
            ->where('user_id', $user->id)
            ->with('productVariant')
            ->latest('id')
            ->get();
    }

    /**
     * @throws ValidationException
     */
    public function add(User $user, int $variantId): Wishlist
    {
        if (! ProductVariant::query()->whereKey($variantId)->exists()) {
            throw ValidationException::withMessages([
                'product_variant_id' => ['Cette variante n’existe pas.'],
            ]);
        }

        try {
            return DB::transaction(function () use ($user, $variantId): Wishlist {
                /** @var Wishlist|null $existing */
                $existing = Wishlist::query()
                    ->where('user_id', $user->id)
                    ->where('product_variant_id', $variantId)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return $existing->load('productVariant');
                }

                $row = new Wishlist([
                    'user_id' => $user->id,
                    'product_variant_id' => $variantId,
                ]);
                $row->save();

                return $row->load('productVariant');
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function remove(Wishlist $wishlist): void
    {
        $wishlist->delete();
    }
}

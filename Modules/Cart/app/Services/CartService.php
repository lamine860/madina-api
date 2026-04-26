<?php

declare(strict_types=1);

namespace Modules\Cart\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Cart\Models\CartItem;
use Modules\Catalog\Exceptions\InsufficientStockException;
use Modules\Catalog\Models\ProductVariant;
use Modules\Catalog\Services\ProductService;
use Modules\Core\Models\User;
use Throwable;

final class CartService
{
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    /**
     * Ajoute au panier ou augmente la quantité sur la ligne unique (user_id, product_variant_id).
     *
     * @throws InsufficientStockException
     * @throws ValidationException
     */
    public function addToCart(User $user, int $variantId, int $quantity): CartItem
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => ['La quantité doit être au moins 1.'],
            ]);
        }

        try {
            return DB::transaction(function () use ($user, $variantId, $quantity): CartItem {
                /** @var CartItem|null $existing */
                $existing = CartItem::query()
                    ->where('user_id', $user->id)
                    ->where('product_variant_id', $variantId)
                    ->lockForUpdate()
                    ->first();

                $targetQuantity = $existing !== null ? $existing->quantity + $quantity : $quantity;

                $this->assertVariantAvailableForQuantity($variantId, $targetQuantity);

                if ($existing !== null) {
                    $existing->quantity = $targetQuantity;
                    $existing->save();

                    return $existing->load('productVariant');
                }

                $item = new CartItem([
                    'user_id' => $user->id,
                    'product_variant_id' => $variantId,
                    'quantity' => $quantity,
                ]);
                $item->save();

                return $item->load('productVariant');
            });
        } catch (InsufficientStockException|ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * @throws InsufficientStockException
     * @throws ValidationException
     */
    public function updateQuantity(CartItem $cartItem, int $newQuantity): void
    {
        if ($newQuantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => ['La quantité doit être au moins 1.'],
            ]);
        }

        try {
            DB::transaction(function () use ($cartItem, $newQuantity): void {
                /** @var CartItem|null $locked */
                $locked = CartItem::query()
                    ->whereKey($cartItem->id)
                    ->lockForUpdate()
                    ->first();

                if ($locked === null) {
                    throw (new ModelNotFoundException)->setModel(CartItem::class, [(string) $cartItem->id]);
                }

                $this->assertVariantAvailableForQuantity((int) $locked->product_variant_id, $newQuantity);

                $locked->quantity = $newQuantity;
                $locked->save();
            });
        } catch (InsufficientStockException|ValidationException|ModelNotFoundException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function removeFromCart(CartItem $cartItem): void
    {
        $cartItem->delete();
    }

    /**
     * Total TTC ligne à ligne (prix variante × quantité), variantes indisponibles ignorées.
     */
    public function getCartTotal(User $user): string
    {
        $items = CartItem::query()
            ->where('user_id', $user->id)
            ->with('productVariant')
            ->get();

        $total = '0.00';

        foreach ($items as $item) {
            $variant = $item->productVariant;
            if ($variant === null) {
                continue;
            }

            $line = bcmul((string) $variant->price, (string) $item->quantity, 2);
            $total = bcadd($total, $line, 2);
        }

        return $total;
    }

    /**
     * Lignes panier verrouillées pour le passage en commande (variante, produit, boutique).
     *
     * @return Collection<int, CartItem>
     */
    public function getCartLinesForCheckout(User $user): Collection
    {
        return CartItem::query()
            ->where('user_id', $user->id)
            ->with(['productVariant.product.shop'])
            ->lockForUpdate()
            ->get();
    }

    public function clearCart(User $user): void
    {
        CartItem::query()->where('user_id', $user->id)->delete();
    }

    /**
     * @throws InsufficientStockException
     * @throws ValidationException
     */
    private function assertVariantAvailableForQuantity(int $variantId, int $quantity): void
    {
        if (! $this->productService->hasStock($variantId, $quantity)) {
            /** @var ProductVariant|null $variant */
            $variant = ProductVariant::query()->find($variantId);

            if ($variant === null) {
                throw ValidationException::withMessages([
                    'product_variant_id' => ['Cette variante n’est pas disponible.'],
                ]);
            }

            throw new InsufficientStockException(
                variantId: $variantId,
                requestedQuantity: $quantity,
                availableQuantity: $variant->stock_qty,
            );
        }
    }
}

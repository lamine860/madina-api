<?php

declare(strict_types=1);

namespace Modules\Orders\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Services\CartService;
use Modules\Catalog\Exceptions\InsufficientStockException;
use Modules\Catalog\Models\ProductVariant;
use Modules\Catalog\Services\ProductService;
use Modules\Core\Models\User;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Events\OrderCreated;
use Modules\Orders\Models\Order;
use Throwable;

final class OrderService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly ProductService $productService,
    ) {}

    /**
     * Passe commande depuis le panier : vérif stock, persistance, décrément catalogue, vidage panier.
     *
     * @param  array{shipping_address: array<string, mixed>, billing_address?: array<string, mixed>|null, notes?: string|null}  $shippingDetails
     *
     * @throws InsufficientStockException
     * @throws ValidationException
     */
    public function checkoutFromCart(User $user, array $shippingDetails): Order
    {
        try {
            $order = DB::transaction(function () use ($user, $shippingDetails): Order {
                /** @var Collection<int, CartItem> $lines */
                $lines = $this->cartService->getCartLinesForCheckout($user);

                if ($lines->isEmpty()) {
                    throw ValidationException::withMessages([
                        'cart' => ['Le panier est vide.'],
                    ]);
                }

                foreach ($lines as $item) {
                    $this->assertCartLineCheckoutable($item);
                    $variantId = (int) $item->product_variant_id;
                    if (! $this->productService->hasStock($variantId, $item->quantity)) {
                        /** @var ProductVariant|null $variant */
                        $variant = ProductVariant::query()->find($variantId);
                        if ($variant === null) {
                            throw ValidationException::withMessages([
                                'cart' => ['Une variante du panier n’est plus disponible.'],
                            ]);
                        }

                        throw new InsufficientStockException(
                            variantId: $variantId,
                            requestedQuantity: $item->quantity,
                            availableQuantity: $variant->stock_qty,
                        );
                    }
                }

                $total = '0.00';
                $rows = [];

                foreach ($lines as $item) {
                    /** @var ProductVariant $variant */
                    $variant = $item->productVariant;
                    $product = $variant->product;
                    $unitPrice = (string) $variant->price;
                    $subtotal = bcmul($unitPrice, (string) $item->quantity, 2);
                    $total = bcadd($total, $subtotal, 2);
                    $rows[] = [
                        'shop_id' => $product->shop_id,
                        'product_variant_id' => $variant->id,
                        'quantity' => $item->quantity,
                        'unit_price' => $unitPrice,
                        'subtotal' => $subtotal,
                    ];
                }

                $order = Order::query()->create([
                    'order_number' => 'TMP-'.Str::lower(Str::random(40)),
                    'user_id' => $user->id,
                    'total_amount' => $total,
                    'status' => OrderStatus::Pending,
                    'shipping_address' => $shippingDetails['shipping_address'],
                    'billing_address' => $shippingDetails['billing_address'] ?? null,
                    'notes' => $shippingDetails['notes'] ?? null,
                ]);

                foreach ($rows as $row) {
                    $order->items()->create($row);
                }

                foreach ($lines as $item) {
                    $this->productService->decrementStock((int) $item->product_variant_id, $item->quantity);
                }

                $this->cartService->clearCart($user);

                $order->order_number = sprintf(
                    'CMD-%s-%06d',
                    $order->created_at?->format('Y') ?? now()->format('Y'),
                    $order->id,
                );
                $order->save();

                return $order->load(['items.productVariant', 'items.shop']);
            });

            OrderCreated::dispatch($order);

            return $order;
        } catch (InsufficientStockException|ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * @throws ValidationException
     */
    public function changeStatus(Order $order, OrderStatus|string $newStatus): void
    {
        $target = $newStatus instanceof OrderStatus
            ? $newStatus
            : OrderStatus::tryFrom((string) $newStatus);

        if ($target === null) {
            throw ValidationException::withMessages([
                'status' => ['Statut inconnu.'],
            ]);
        }

        $current = $order->status;

        if ($current === $target) {
            return;
        }

        if (! $this->isAllowedTransition($current, $target)) {
            throw ValidationException::withMessages([
                'status' => [sprintf(
                    'Transition interdite de « %s » vers « %s ».',
                    $current->value,
                    $target->value,
                )],
            ]);
        }

        $order->status = $target;
        $order->save();
    }

    private function assertCartLineCheckoutable(CartItem $item): void
    {
        $variant = $item->productVariant;

        if ($variant === null) {
            throw ValidationException::withMessages([
                'cart' => ['Une ligne du panier référence une variante indisponible.'],
            ]);
        }

        if ($variant->trashed()) {
            throw ValidationException::withMessages([
                'cart' => ['Une variante du panier n’est plus disponible.'],
            ]);
        }

        $product = $variant->product;

        if ($product === null || ! $product->is_active) {
            throw ValidationException::withMessages([
                'cart' => ['Un produit du panier n’est plus disponible à la vente.'],
            ]);
        }
    }

    private function isAllowedTransition(OrderStatus $from, OrderStatus $to): bool
    {
        return match ($from) {
            OrderStatus::Pending => in_array($to, [OrderStatus::Paid, OrderStatus::Cancelled], true),
            OrderStatus::Paid => in_array($to, [OrderStatus::Processing, OrderStatus::Cancelled, OrderStatus::Refunded], true),
            OrderStatus::Processing => in_array($to, [OrderStatus::Shipped, OrderStatus::Cancelled], true),
            OrderStatus::Shipped => $to === OrderStatus::Refunded,
            OrderStatus::Cancelled, OrderStatus::Refunded => false,
        };
    }
}

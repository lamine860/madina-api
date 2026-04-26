<?php

declare(strict_types=1);

namespace Modules\Cart\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Cart\Http\Requests\StoreCartItemRequest;
use Modules\Cart\Http\Requests\UpdateCartItemRequest;
use Modules\Cart\Http\Resources\CartItemResource;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Services\CartService;
use Modules\Core\Models\User;

final class CartController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CartService $cartService,
    ) {}

    /**
     * @group Panier
     *
     * @subgroup Articles
     *
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $items = CartItem::query()
            ->where('user_id', $user->id)
            ->with('productVariant')
            ->latest('id')
            ->get();

        $total = $this->cartService->getCartTotal($user);

        return response()->json([
            'items' => CartItemResource::collection($items),
            'total' => $total,
        ]);
    }

    /**
     * @group Panier
     *
     * @subgroup Articles
     *
     * @authenticated
     */
    public function store(StoreCartItemRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $item = $this->cartService->addToCart(
            $user,
            (int) $validated['product_variant_id'],
            (int) $validated['quantity'],
        );

        return response()->json([
            'item' => new CartItemResource($item),
            'total' => $this->cartService->getCartTotal($user),
        ], 201);
    }

    /**
     * @group Panier
     *
     * @subgroup Articles
     *
     * @authenticated
     */
    public function update(UpdateCartItemRequest $request, int $cart_item): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $cartItem = CartItem::query()
            ->whereKey($cart_item)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->authorize('update', $cartItem);

        $quantity = (int) $request->validated('quantity');

        $this->cartService->updateQuantity($cartItem, $quantity);
        $cartItem->refresh()->load('productVariant');

        return response()->json([
            'item' => new CartItemResource($cartItem),
            'total' => $this->cartService->getCartTotal($user),
        ]);
    }

    /**
     * @group Panier
     *
     * @subgroup Articles
     *
     * @authenticated
     */
    public function destroy(Request $request, int $cart_item): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $cartItem = CartItem::query()
            ->whereKey($cart_item)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->authorize('delete', $cartItem);

        $this->cartService->removeFromCart($cartItem);

        return response()->json([
            'total' => $this->cartService->getCartTotal($user),
        ]);
    }
}

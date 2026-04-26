<?php

declare(strict_types=1);

namespace Modules\Orders\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Enums\UserRole;
use Modules\Core\Models\User;
use Modules\Orders\Http\Requests\CheckoutOrderRequest;
use Modules\Orders\Http\Requests\UpdateOrderStatusRequest;
use Modules\Orders\Http\Resources\OrderResource;
use Modules\Orders\Models\Order;
use Modules\Orders\Services\OrderService;

final class OrderController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    /**
     * @group Commandes
     *
     * @subgroup Acheteur
     *
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        /** @var User $user */
        $user = $request->user();

        $orders = Order::query()
            ->where('user_id', $user->id)
            ->with(['items.productVariant'])
            ->latest('id')
            ->paginate(perPage: 15);

        return OrderResource::collection($orders)->response();
    }

    /**
     * @group Commandes
     *
     * @subgroup Acheteur
     *
     * @authenticated
     */
    public function show(Request $request, int $order): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = Order::query()
            ->whereKey($order)
            ->with(['items.productVariant', 'items.shop']);

        if ($user->role !== UserRole::Admin) {
            $user->loadMissing('shop');
            $query->where(function ($inner) use ($user): void {
                $inner->where('user_id', $user->id);
                if ($user->shop !== null) {
                    $inner->orWhereHas('items', static function ($items) use ($user): void {
                        $items->where('shop_id', $user->shop->id);
                    });
                }
            });
        }

        $orderModel = $query->firstOrFail();

        $this->authorize('view', $orderModel);

        return response()->json([
            'order' => new OrderResource($orderModel),
        ]);
    }

    /**
     * @group Commandes
     *
     * @subgroup Acheteur
     *
     * @authenticated
     */
    public function checkout(CheckoutOrderRequest $request): JsonResponse
    {
        $this->authorize('create', Order::class);

        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $order = $this->orderService->checkoutFromCart($user, $validated);

        return response()->json([
            'order' => new OrderResource($order),
        ], 201);
    }

    /**
     * @group Commandes
     *
     * @subgroup Administration
     *
     * @authenticated
     */
    public function updateStatus(UpdateOrderStatusRequest $request, int $order): JsonResponse
    {
        $orderModel = Order::query()
            ->whereKey($order)
            ->with(['items.productVariant'])
            ->firstOrFail();

        $this->authorize('updateStatus', $orderModel);

        $this->orderService->changeStatus($orderModel, $request->validated('status'));
        $orderModel->refresh()->load(['items.productVariant']);

        return response()->json([
            'order' => new OrderResource($orderModel),
        ]);
    }
}

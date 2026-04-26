<?php

declare(strict_types=1);

namespace Modules\Orders\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Orders\Http\Resources\OrderResource;
use Modules\Orders\Models\Order;
use Modules\Shop\Models\Shop;

final class ShopOrderController extends Controller
{
    use AuthorizesRequests;

    /**
     * @group Commandes
     *
     * @subgroup Vendeur
     *
     * @authenticated
     */
    public function index(Request $request, Shop $shop): JsonResponse
    {
        $this->authorize('viewOrders', $shop);

        $orders = Order::query()
            ->whereHas('items', static function ($query) use ($shop): void {
                $query->where('shop_id', $shop->id);
            })
            ->with(['items' => static function ($query) use ($shop): void {
                $query->where('shop_id', $shop->id)->with(['productVariant', 'shop']);
            }])
            ->latest('id')
            ->paginate(perPage: 15);

        return OrderResource::collection($orders)->response();
    }
}

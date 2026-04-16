<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Shop\Entities\Shop;
use Modules\Shop\Http\Requests\CreateShopRequest;
use Modules\Shop\Http\Requests\UpdateShopRequest;
use Modules\Shop\Http\Resources\ShopResource;
use Modules\Shop\Services\ShopService;

final class ShopController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ShopService $shopService,
    ) {}

    public function store(CreateShopRequest $request): JsonResponse
    {
        $this->authorize('create', Shop::class);

        $validated = $request->validated();
        $payload = collect($validated)->except('logo')->all();

        $shop = $this->shopService->createShop(
            $request->user(),
            $payload,
            $request->file('logo'),
        );

        return response()->json([
            'shop' => new ShopResource($shop),
        ], 201);
    }



    public function update(UpdateShopRequest $request, Shop $shop): JsonResponse
    {
        $this->authorize('update', $shop);

        $validated = $request->validated();
        $payload = collect($validated)->except('logo')->all();

        $shop = $this->shopService->updateShop(
            $shop,
            $payload,
            $request->file('logo'),
        );

        return response()->json([
            'shop' => new ShopResource($shop),
        ]);
    }

    public function show(Shop $shop): JsonResponse
    {
        return response()->json([
            'shop' => new ShopResource($shop),
        ]);
    }
}

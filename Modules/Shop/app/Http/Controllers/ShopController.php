<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Shop\Http\Requests\CreateShopRequest;
use Modules\Shop\Http\Requests\UpdateShopRequest;
use Modules\Shop\Http\Resources\ShopResource;
use Modules\Shop\Models\Shop;
use Modules\Shop\Services\ShopService;

final class ShopController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ShopService $shopService,
    ) {}

    /**
     * @group Boutique
     *
     * @subgroup Création
     *
     * @authenticated
     */
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

    /**
     * @group Boutique
     *
     * @subgroup Mise à jour
     *
     * @authenticated
     */
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

    /**
     * Affiche le détail public d’une boutique par son slug (sans authentification).
     *
     * @group Boutique
     *
     * @subgroup Consultation
     *
     * @urlParam shop string required Slug unique de la boutique. Example: kilora-tech-store
     *
     * @response 200 {
     *   "shop": {
     *     "id": 1,
     *     "user_id": 2,
     *     "name": "Kilora Tech Store",
     *     "slug": "kilora-tech-store",
     *     "description": "Vente de matériel informatique et accessoires.",
     *     "logo_url": "https://cdn.example.com/shops/1/logo.png",
     *     "company_name": "Kilora SARL",
     *     "vat_number": "RC-CON-2024-B-1234",
     *     "is_verified": true,
     *     "created_at": "2026-05-17T10:00:00+00:00",
     *     "updated_at": "2026-05-17T10:00:00+00:00"
     *   }
     * }
     * @response 404 scenario="Boutique introuvable"
     */
    public function show(Shop $shop): JsonResponse
    {
        return response()->json([
            'shop' => new ShopResource($shop),
        ]);
    }

    /**
     * @group Boutique
     *
     * @subgroup Suppression
     *
     * @authenticated
     */
    public function destroy(Shop $shop): Response
    {
        $this->authorize('delete', $shop);

        $this->shopService->deleteShop($shop);

        return response()->noContent();
    }
}

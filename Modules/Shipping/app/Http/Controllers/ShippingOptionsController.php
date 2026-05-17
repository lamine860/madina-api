<?php

declare(strict_types=1);

namespace Modules\Shipping\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Shipping\Http\Requests\ShippingOptionsRequest;
use Modules\Shipping\Services\ShippingService;

final class ShippingOptionsController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ShippingService $shippingService,
    ) {}

    /**
     * Retourne les options de livraison Kilora (tarifs et fenêtres indicatives) pour un quartier donné.
     *
     * Sans `neighborhood_slug`, des délais conservateurs s’appliquent pour FLASH (150–240 min).
     * Les prix proviennent des tarifs en base (`FLASH`, `DIRECT`, `ECO`).
     *
     * @group Livraison
     *
     * Estimation des tarifs et délais, vérification du retrait et de la livraison.
     * Les versements vendeurs (`pending` → `ready`) sont déclenchés lors des vérifications logistiques ;
     * voir aussi `PayoutService` dans le module Payouts.
     *
     * @subgroup Estimation
     *
     * @authenticated
     *
     * @response 200 scenario="Zone A (madina)" {
     *   "zone": "Zone A",
     *   "neighborhood_slug": "madina",
     *   "neighborhood_warning": null,
     *   "options": [
     *     {
     *       "code": "FLASH",
     *       "name": "Kilora Flash",
     *       "price": "50000.00",
     *       "eta_min_minutes": 90,
     *       "eta_max_minutes": 150
     *     }
     *   ]
     * }
     */
    public function index(ShippingOptionsRequest $request): JsonResponse
    {
        $slug = $request->validated('neighborhood_slug');
        $result = $this->shippingService->calculateEstimation($slug);

        return response()->json($result->toArray());
    }
}

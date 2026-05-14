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

    public function index(ShippingOptionsRequest $request): JsonResponse
    {
        $slug = $request->validated('neighborhood_slug');
        $result = $this->shippingService->calculateEstimation($slug);

        return response()->json($result->toArray());
    }
}

<?php

declare(strict_types=1);

namespace Modules\Shipping\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Shipping\Http\Requests\VerifyDeliveryRequest;
use Modules\Shipping\Http\Requests\VerifyPickupRequest;
use Modules\Shipping\Models\Shipment;
use Modules\Shipping\Services\ShippingService;

final class ShipmentVerificationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ShippingService $shippingService,
    ) {}

    public function verifyPickup(VerifyPickupRequest $request): JsonResponse
    {
        $data = $request->validated();
        /** @var Shipment $shipment */
        $shipment = Shipment::query()->findOrFail((int) $data['shipment_id']);
        $this->authorize('verifyPickup', $shipment);

        $order = $shipment->order;
        $updated = $this->shippingService->verifyPickup(
            $order,
            (int) $data['shipment_id'],
            $data['exit_code'] ?? null,
        );

        return response()->json([
            'message' => 'Retrait validé.',
            'shipment' => [
                'id' => $updated->id,
                'status' => $updated->status->value,
            ],
        ]);
    }

    public function verifyDelivery(VerifyDeliveryRequest $request): JsonResponse
    {
        $data = $request->validated();
        /** @var Shipment $shipment */
        $shipment = Shipment::query()->findOrFail((int) $data['shipment_id']);
        $this->authorize('verifyDelivery', $shipment);

        $order = $shipment->order;
        $updated = $this->shippingService->verifyDelivery(
            $order,
            (int) $data['shipment_id'],
            (string) $data['confirmation_code'],
        );

        return response()->json([
            'message' => 'Livraison validée.',
            'shipment' => [
                'id' => $updated->id,
                'status' => $updated->status->value,
            ],
        ]);
    }
}

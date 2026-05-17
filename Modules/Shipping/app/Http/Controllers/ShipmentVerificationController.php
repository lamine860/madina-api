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

    /**
     * Valide le retrait d’un colis par le vendeur (code sortie) pour une expédition Kilora.
     *
     * Autorisation : vendeur propriétaire de la boutique de l’expédition, ou administrateur.
     * Appel idempotent : un second appel avec le même code valide renvoie toujours 200.
     *
     * **Versements vendeurs** : si le transporteur a `payout_trigger = pickup` (Kilora Internal),
     * le versement de la boutique passe de `pending` à `ready`.
     *
     * @group Livraison
     *
     * @subgroup Vérification logistique
     *
     * @authenticated
     *
     * @response 200 {
     *   "message": "Retrait validé.",
     *   "shipment": {
     *     "id": 1,
     *     "status": "picked_up"
     *   }
     * }
     * @response 422 scenario="Code sortie invalide" {
     *   "message": "Le code sortie est obligatoire pour la livraison Kilora.",
     *   "errors": {
     *     "exit_code": ["Code sortie invalide."]
     *   }
     * }
     * @response 403 scenario="Vendeur non autorisé"
     */
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

    /**
     * Valide la livraison finale au client via le code de confirmation.
     *
     * **Kilora** : le colis doit être en statut `picked_up` avant la livraison.
     * **Auto-livraison boutique** : le pickup n’est pas requis ; seul ce endpoint libère le versement.
     *
     * **Versements vendeurs** : si le transporteur a `payout_trigger = delivery` (boutique),
     * le versement passe de `pending` à `ready`. Lorsque toutes les expéditions (hors annulées)
     * sont livrées, la commande passe en statut `shipped`.
     *
     * @group Livraison
     *
     * @subgroup Vérification logistique
     *
     * @authenticated
     *
     * @response 200 {
     *   "message": "Livraison validée.",
     *   "shipment": {
     *     "id": 1,
     *     "status": "delivered"
     *   }
     * }
     * @response 422 scenario="Pickup requis (Kilora)" {
     *   "message": "Le colis doit être retiré (pickup) avant la livraison finale.",
     *   "errors": {
     *     "shipment_id": ["Le colis doit être retiré (pickup) avant la livraison finale."]
     *   }
     * }
     * @response 403 scenario="Vendeur non autorisé"
     */
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

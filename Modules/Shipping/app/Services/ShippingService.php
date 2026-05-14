<?php

declare(strict_types=1);

namespace Modules\Shipping\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;
use Modules\Orders\Services\OrderService;
use Modules\Payouts\Services\PayoutService;
use Modules\Shipping\DTOs\ShippingEstimationResult;
use Modules\Shipping\DTOs\ShippingOptionEstimate;
use Modules\Shipping\Enums\DeliveryMode;
use Modules\Shipping\Enums\DeliveryProviderType;
use Modules\Shipping\Enums\PayoutTrigger;
use Modules\Shipping\Enums\ShipmentStatus;
use Modules\Shipping\Models\DeliveryProvider;
use Modules\Shipping\Models\DeliveryZone;
use Modules\Shipping\Models\Shipment;
use Modules\Shipping\Models\ShippingRate;

final class ShippingService
{
    public function __construct(
        private readonly PayoutService $payoutService,
        private readonly CodeGenerator $codeGenerator,
        private readonly OrderService $orderService,
    ) {}

    /**
     * Tarifs et fenêtres indicatives (source PDF Kilora). Les prix viennent des lignes `shipping_services`.
     */
    public function calculateEstimation(?string $neighborhoodSlug): ShippingEstimationResult
    {
        $slug = $neighborhoodSlug !== null && $neighborhoodSlug !== ''
            ? strtolower($neighborhoodSlug)
            : null;

        $zone = $slug !== null ? $this->findZoneByNeighborhoodSlug($slug) : null;
        $warning = $slug === null ? 'Quartier non fourni : délais conservateurs appliqués pour FLASH.' : null;

        $isZoneA = $zone !== null && str_contains(mb_strtolower($zone->name), 'zone a');

        /** @var list<ShippingOptionEstimate> $options */
        $options = [];
        $rates = ShippingRate::query()->orderBy('code')->get();

        foreach ($rates as $rate) {
            $code = (string) $rate->code;
            $price = (string) $rate->base_price;

            if ($code === 'FLASH') {
                if ($isZoneA) {
                    $options[] = new ShippingOptionEstimate($code, (string) $rate->name, $price, 90, 150);
                } else {
                    $options[] = new ShippingOptionEstimate($code, (string) $rate->name, $price, 150, 240);
                }
            } elseif ($code === 'DIRECT') {
                $options[] = new ShippingOptionEstimate($code, (string) $rate->name, $price, 60, 720);
            } elseif ($code === 'ECO') {
                $options[] = new ShippingOptionEstimate($code, (string) $rate->name, $price, 180, 4320);
            }
        }

        if ($slug !== null && $zone === null) {
            $warning = 'Quartier inconnu : délais conservateurs (Zone B) utilisés pour FLASH.';
        }

        return new ShippingEstimationResult(
            $zone?->name,
            $slug,
            $options,
            $warning,
        );
    }

    public function bootstrapFulfillmentForPaidOrder(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::Paid) {
                return;
            }

            /** @var DeliveryProvider $kilora */
            $kilora = DeliveryProvider::query()
                ->where('type', DeliveryProviderType::Internal)
                ->where('name', 'Kilora Internal')
                ->firstOrFail();

            $serviceCode = (string) config('shipping.default_service_code', 'FLASH');
            /** @var ShippingRate $service */
            $service = ShippingRate::query()->where('code', $serviceCode)->firstOrFail();

            $shopIds = $locked->items()->distinct()->pluck('shop_id');

            foreach ($shopIds as $shopId) {
                $shopId = (int) $shopId;
                $this->payoutService->createPendingForShopIfMissing($locked, $shopId);

                $shipmentExists = Shipment::query()
                    ->where('order_id', $locked->id)
                    ->where('shop_id', $shopId)
                    ->lockForUpdate()
                    ->exists();

                if ($shipmentExists) {
                    continue;
                }

                Shipment::query()->create([
                    'order_id' => $locked->id,
                    'shop_id' => $shopId,
                    'provider_id' => $kilora->id,
                    'service_id' => $service->id,
                    'exit_code' => $this->codeGenerator->generateUniqueExitCode(),
                    'confirmation_code' => $this->codeGenerator->generateUniqueConfirmationCode(),
                    'status' => ShipmentStatus::Pending,
                    'delivery_mode' => DeliveryMode::KiloraDelivery,
                ]);
            }
        });

        $this->syncOrderProcessingIfPaid($order->fresh());
    }

    public function verifyPickup(Order $order, int $shipmentId, ?string $exitCode): Shipment
    {
        return DB::transaction(function () use ($order, $shipmentId, $exitCode): Shipment {
            /** @var Shipment $shipment */
            $shipment = Shipment::query()
                ->whereKey($shipmentId)
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertOrderPaidForLogistics($order);

            if ($shipment->status === ShipmentStatus::PickedUp) {
                return $shipment;
            }

            if ($shipment->delivery_mode === DeliveryMode::ShopSelfDelivery) {
                throw ValidationException::withMessages([
                    'exit_code' => ['Le retrait avec code sortie ne s’applique pas à l’auto-livraison boutique.'],
                ]);
            }

            if ($exitCode === null || $exitCode === '') {
                throw ValidationException::withMessages([
                    'exit_code' => ['Le code sortie est obligatoire pour la livraison Kilora.'],
                ]);
            }

            if (! hash_equals((string) $shipment->exit_code, $exitCode)) {
                throw ValidationException::withMessages([
                    'exit_code' => ['Code sortie invalide.'],
                ]);
            }

            $shipment->update([
                'status' => ShipmentStatus::PickedUp,
                'pickup_verified_at' => now(),
            ]);

            $shipment->load('provider');
            $provider = $shipment->provider;
            if ($provider->payout_trigger === PayoutTrigger::Pickup) {
                $this->payoutService->markReady($order->fresh(), (int) $shipment->shop_id);
            }

            return Shipment::query()->whereKey($shipment->id)->firstOrFail();
        });
    }

    public function verifyDelivery(Order $order, int $shipmentId, string $confirmationCode): Shipment
    {
        return DB::transaction(function () use ($order, $shipmentId, $confirmationCode): Shipment {
            /** @var Shipment $shipment */
            $shipment = Shipment::query()
                ->whereKey($shipmentId)
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertOrderPaidForLogistics($order);

            if ($shipment->status === ShipmentStatus::Delivered) {
                return $shipment;
            }

            if ($shipment->status === ShipmentStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'shipment_id' => ['Cette expédition est annulée.'],
                ]);
            }

            if ($shipment->delivery_mode === DeliveryMode::KiloraDelivery && $shipment->status !== ShipmentStatus::PickedUp) {
                throw ValidationException::withMessages([
                    'shipment_id' => ['Le colis doit être retiré (pickup) avant la livraison finale.'],
                ]);
            }

            if (! hash_equals((string) $shipment->confirmation_code, $confirmationCode)) {
                throw ValidationException::withMessages([
                    'confirmation_code' => ['Code de confirmation invalide.'],
                ]);
            }

            $shipment->update([
                'status' => ShipmentStatus::Delivered,
                'delivery_verified_at' => now(),
            ]);

            $shipment->load('provider');
            $provider = $shipment->provider;
            if ($provider->payout_trigger === PayoutTrigger::Delivery) {
                $this->payoutService->markReady($order->fresh(), (int) $shipment->shop_id);
            }

            $freshOrder = $order->fresh();
            if ($freshOrder !== null) {
                $this->syncOrderShippedWhenAllDelivered($freshOrder);
            }

            return Shipment::query()->whereKey($shipment->id)->firstOrFail();
        });
    }

    private function assertOrderPaidForLogistics(Order $order): void
    {
        if (! in_array($order->status, [OrderStatus::Paid, OrderStatus::Processing], true)) {
            throw ValidationException::withMessages([
                'order' => ['La commande doit être payée avant cette opération de logistique.'],
            ]);
        }
    }

    private function findZoneByNeighborhoodSlug(string $slug): ?DeliveryZone
    {
        /** @var DeliveryZone $zone */
        foreach (DeliveryZone::query()->get() as $zone) {
            /** @var array<int, array{slug?: string, label?: string}>|null $rows */
            $rows = $zone->neighborhoods;
            if (! is_array($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                if (isset($row['slug']) && strtolower((string) $row['slug']) === $slug) {
                    return $zone;
                }
            }
        }

        return null;
    }

    /**
     * Règle métier : lorsque des expéditions existent après paiement, la commande passe en traitement logistique.
     */
    private function syncOrderProcessingIfPaid(Order $order): void
    {
        $hasShipments = Shipment::query()->where('order_id', $order->id)->exists();
        if (! $hasShipments) {
            return;
        }

        if ($order->status === OrderStatus::Paid) {
            $this->orderService->changeStatus($order, OrderStatus::Processing);
        }
    }

    /**
     * Règle métier : la commande est marquée livrée côté plateforme lorsque toutes les expéditions (hors annulées) sont DELIVERED.
     */
    private function syncOrderShippedWhenAllDelivered(Order $order): void
    {
        $total = Shipment::query()
            ->where('order_id', $order->id)
            ->where('status', '!=', ShipmentStatus::Cancelled)
            ->count();

        if ($total === 0) {
            return;
        }

        $delivered = Shipment::query()
            ->where('order_id', $order->id)
            ->where('status', ShipmentStatus::Delivered)
            ->count();

        if ($delivered !== $total) {
            return;
        }

        if ($order->status === OrderStatus::Processing) {
            $this->orderService->changeStatus($order, OrderStatus::Shipped);
        }
    }
}

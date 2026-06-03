<?php

declare(strict_types=1);

namespace Modules\Notification\Observers;

use Modules\Notification\Services\OrderSmsNotifier;
use Modules\Notification\Services\SellerSmsNotifier;
use Modules\Shipping\Models\Shipment;

final class ShipmentSmsObserver
{
    public function __construct(
        private readonly OrderSmsNotifier $orderSmsNotifier,
        private readonly SellerSmsNotifier $sellerSmsNotifier,
    ) {}

    public function created(Shipment $shipment): void
    {
        $this->orderSmsNotifier->notifyShipmentReady($shipment);
        $this->sellerSmsNotifier->notifyShipmentExitCode($shipment);
    }
}

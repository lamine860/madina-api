<?php

declare(strict_types=1);

namespace Modules\Notification\Services;

use Modules\Notification\Notifications\SellerSmsMessage;
use Modules\Orders\Models\Order;
use Modules\Shipping\Models\Shipment;
use Modules\Shop\Models\Shop;

final class SellerSmsNotifier
{
    public function __construct(
        private readonly SmsService $smsService,
        private readonly UserPhoneResolver $phoneResolver,
    ) {}

    public function notifyNewPaidOrder(Shop $shop, Order $order): void
    {
        $shop->loadMissing('user');

        $phone = $this->phoneResolver->resolve($shop->user);

        if ($phone === null) {
            return;
        }

        $this->smsService->send(
            $phone,
            SellerSmsMessage::newPaidOrder($order->order_number),
        );
    }

    public function notifyShipmentExitCode(Shipment $shipment): void
    {
        $shipment->loadMissing('order', 'shop.user');

        $phone = $this->phoneResolver->resolve($shipment->shop?->user);

        if ($phone === null) {
            return;
        }

        $exitCode = (string) $shipment->exit_code;

        if ($exitCode === '') {
            return;
        }

        $this->smsService->send(
            $phone,
            SellerSmsMessage::shipmentExitCode($shipment->order->order_number, $exitCode),
        );
    }
}

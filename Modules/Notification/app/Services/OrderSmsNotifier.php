<?php

declare(strict_types=1);

namespace Modules\Notification\Services;

use Modules\Notification\Notifications\OrderSmsMessage;
use Modules\Orders\Models\Order;
use Modules\Shipping\Models\Shipment;

final class OrderSmsNotifier
{
    public function __construct(
        private readonly SmsService $smsService,
        private readonly UserPhoneResolver $phoneResolver,
    ) {}

    public function notifyOrderPaid(Order $order): void
    {
        $phone = $this->resolvePhone($order);

        if ($phone === null) {
            return;
        }

        $this->smsService->send(
            $phone,
            OrderSmsMessage::orderPaid($order->order_number),
        );
    }

    public function notifyShipmentReady(Shipment $shipment): void
    {
        $shipment->loadMissing('order.user.customer');

        $phone = $this->resolvePhone($shipment->order);

        if ($phone === null) {
            return;
        }

        $confirmationCode = (string) $shipment->confirmation_code;

        if ($confirmationCode === '') {
            return;
        }

        $this->smsService->send(
            $phone,
            OrderSmsMessage::shipmentReady($confirmationCode),
        );
    }

    private function resolvePhone(Order $order): ?string
    {
        $order->loadMissing('user');

        return $this->phoneResolver->resolve($order->user);
    }
}

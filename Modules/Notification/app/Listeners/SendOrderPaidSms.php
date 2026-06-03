<?php

declare(strict_types=1);

namespace Modules\Notification\Listeners;

use Modules\Notification\Services\OrderSmsNotifier;
use Modules\Orders\Events\OrderPaid;

final class SendOrderPaidSms
{
    public function __construct(
        private readonly OrderSmsNotifier $orderSmsNotifier,
    ) {}

    public function handle(OrderPaid $event): void
    {
        $this->orderSmsNotifier->notifyOrderPaid($event->order);
    }
}

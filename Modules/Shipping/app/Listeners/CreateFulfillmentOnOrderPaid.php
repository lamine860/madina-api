<?php

declare(strict_types=1);

namespace Modules\Shipping\Listeners;

use Modules\Orders\Events\OrderPaid;
use Modules\Shipping\Services\ShippingService;

final class CreateFulfillmentOnOrderPaid
{
    public function __construct(
        private readonly ShippingService $shippingService,
    ) {}

    public function handle(OrderPaid $event): void
    {
        $this->shippingService->bootstrapFulfillmentForPaidOrder($event->order);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Orders\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Orders\Events\OrderCreated;

final class SendOrderConfirmation
{
    public function handle(OrderCreated $event): void
    {
        Log::info('orders.confirmation.placeholder', [
            'order_id' => $event->order->id,
            'order_number' => $event->order->order_number,
        ]);
    }
}

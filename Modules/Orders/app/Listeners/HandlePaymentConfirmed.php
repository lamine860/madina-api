<?php

declare(strict_types=1);

namespace Modules\Orders\Listeners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;
use Modules\Payments\Events\PaymentConfirmed;

final class HandlePaymentConfirmed
{
    public function handle(PaymentConfirmed $event): void
    {
        DB::transaction(function () use ($event): void {
            /** @var Order $order */
            $order = Order::query()->whereKey($event->order->id)->lockForUpdate()->firstOrFail();

            if ($order->status === OrderStatus::Paid) {
                return;
            }

            $order->update([
                'status' => OrderStatus::Paid,
            ]);
        });

        Log::info('orders.invoice.placeholder', [
            'order_id' => $event->order->id,
            'payment_id' => $event->payment->id,
        ]);
    }
}

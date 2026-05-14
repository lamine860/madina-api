<?php

declare(strict_types=1);

namespace Modules\Payouts\Services;

use Illuminate\Support\Facades\DB;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderItem;
use Modules\Payouts\Enums\PayoutStatus;
use Modules\Payouts\Models\Payout;

final class PayoutService
{
    /**
     * Crée un payout PENDING pour une boutique sur une commande (idempotent).
     */
    public function createPendingForShopIfMissing(Order $order, int $shopId): Payout
    {
        return DB::transaction(function () use ($order, $shopId): Payout {
            /** @var Payout|null $locked */
            $locked = Payout::query()
                ->where('order_id', $order->id)
                ->where('shop_id', $shopId)
                ->lockForUpdate()
                ->first();

            if ($locked !== null) {
                return $locked;
            }

            $subtotal = $this->sumSubtotalForShop($order->id, $shopId);
            $rate = (string) config('payouts.commission_rate', '0.10');
            $commission = bcmul($subtotal, $rate, 2);
            $net = bcsub($subtotal, $commission, 2);

            return Payout::query()->create([
                'shop_id' => $shopId,
                'order_id' => $order->id,
                'amount' => $net,
                'commission' => $commission,
                'status' => PayoutStatus::Pending,
                'currency' => 'GNF',
                'idempotency_key' => sprintf('order-%d-shop-%d', $order->id, $shopId),
            ]);
        });
    }

    /**
     * Passe le payout en READY si encore PENDING (idempotent).
     */
    public function markReady(Order $order, int $shopId): void
    {
        if (! $this->orderAllowsPayoutRelease($order)) {
            return;
        }

        DB::transaction(function () use ($order, $shopId): void {
            /** @var Payout|null $payout */
            $payout = Payout::query()
                ->where('order_id', $order->id)
                ->where('shop_id', $shopId)
                ->lockForUpdate()
                ->first();

            if ($payout === null || $payout->status !== PayoutStatus::Pending) {
                return;
            }

            $payout->update([
                'status' => PayoutStatus::Ready,
            ]);
        });
    }

    private function orderAllowsPayoutRelease(Order $order): bool
    {
        return in_array($order->status, [
            OrderStatus::Paid,
            OrderStatus::Processing,
            OrderStatus::Shipped,
        ], true);
    }

    private function sumSubtotalForShop(int $orderId, int $shopId): string
    {
        $total = '0.00';
        /** @var iterable<OrderItem> $items */
        $items = OrderItem::query()
            ->where('order_id', $orderId)
            ->where('shop_id', $shopId)
            ->get();

        foreach ($items as $item) {
            $total = bcadd($total, (string) $item->subtotal, 2);
        }

        return $total;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Notification\Listeners;

use Modules\Notification\Services\SellerSmsNotifier;
use Modules\Orders\Events\OrderPaid;
use Modules\Shop\Models\Shop;

final class SendSellerOrderPaidSms
{
    public function __construct(
        private readonly SellerSmsNotifier $sellerSmsNotifier,
    ) {}

    public function handle(OrderPaid $event): void
    {
        $order = $event->order;
        $order->loadMissing('items');

        $shopIds = $order->items->pluck('shop_id')->unique()->values();

        if ($shopIds->isEmpty()) {
            return;
        }

        Shop::query()
            ->whereIn('id', $shopIds)
            ->with('user')
            ->get()
            ->each(fn (Shop $shop): mixed => $this->sellerSmsNotifier->notifyNewPaidOrder($shop, $order));
    }
}

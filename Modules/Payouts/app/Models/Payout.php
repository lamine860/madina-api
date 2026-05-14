<?php

declare(strict_types=1);

namespace Modules\Payouts\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Orders\Models\Order;
use Modules\Payouts\Enums\PayoutStatus;
use Modules\Shop\Models\Shop;

#[Fillable([
    'shop_id',
    'order_id',
    'amount',
    'commission',
    'status',
    'currency',
    'idempotency_key',
])]
class Payout extends Model
{
    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'commission' => 'decimal:2',
            'status' => PayoutStatus::class,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Orders\Models\Order;
use Modules\Shipping\Enums\DeliveryMode;
use Modules\Shipping\Enums\ShipmentStatus;
use Modules\Shop\Models\Shop;

#[Fillable([
    'order_id',
    'shop_id',
    'provider_id',
    'service_id',
    'exit_code',
    'confirmation_code',
    'status',
    'delivery_mode',
    'pickup_verified_at',
    'delivery_verified_at',
    'metadata',
])]
class Shipment extends Model
{
    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return BelongsTo<DeliveryProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(DeliveryProvider::class, 'provider_id');
    }

    /**
     * @return BelongsTo<ShippingRate, $this>
     */
    public function rate(): BelongsTo
    {
        return $this->belongsTo(ShippingRate::class, 'service_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'delivery_mode' => DeliveryMode::class,
            'pickup_verified_at' => 'datetime',
            'delivery_verified_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}

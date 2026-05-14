<?php

declare(strict_types=1);

namespace Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Shipping\Enums\DeliveryProviderType;
use Modules\Shipping\Enums\PayoutTrigger;

#[Fillable(['name', 'type', 'payout_trigger'])]
class DeliveryProvider extends Model
{
    /**
     * @return HasMany<Shipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'provider_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DeliveryProviderType::class,
            'payout_trigger' => PayoutTrigger::class,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'neighborhoods'])]
class DeliveryZone extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'neighborhoods' => 'array',
        ];
    }
}

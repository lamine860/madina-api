<?php

declare(strict_types=1);

namespace Modules\Shipping\Enums;

enum ShipmentStatus: string
{
    case Pending = 'pending';
    case PickedUp = 'picked_up';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
}

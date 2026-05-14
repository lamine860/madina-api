<?php

declare(strict_types=1);

namespace Modules\Shipping\Enums;

enum PayoutTrigger: string
{
    case Pickup = 'pickup';
    case Delivery = 'delivery';
}

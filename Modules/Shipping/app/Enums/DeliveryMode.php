<?php

declare(strict_types=1);

namespace Modules\Shipping\Enums;

enum DeliveryMode: string
{
    case KiloraDelivery = 'kilora_delivery';
    case ShopSelfDelivery = 'shop_self_delivery';
}

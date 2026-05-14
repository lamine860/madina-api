<?php

declare(strict_types=1);

namespace Modules\Shipping\Enums;

enum DeliveryProviderType: string
{
    case Internal = 'internal';
    case Partner = 'partner';
    case Shop = 'shop';
}

<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

enum AddressType: string
{
    case Shipping = 'shipping';
    case Billing = 'billing';
}

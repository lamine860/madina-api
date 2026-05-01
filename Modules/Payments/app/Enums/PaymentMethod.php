<?php

declare(strict_types=1);

namespace Modules\Payments\Enums;

enum PaymentMethod: string
{
    case Orange = 'orange';
    case Moov = 'moov';
    case Card = 'card';
    case Kulu = 'kulu';
    case Wave = 'wave';
}

<?php

declare(strict_types=1);

namespace Modules\Payouts\Enums;

enum PayoutStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Paid = 'paid';
}

<?php

declare(strict_types=1);

namespace Modules\Orders\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
}

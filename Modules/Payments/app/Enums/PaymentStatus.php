<?php

declare(strict_types=1);

namespace Modules\Payments\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
}

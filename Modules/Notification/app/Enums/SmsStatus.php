<?php

declare(strict_types=1);

namespace Modules\Notification\Enums;

enum SmsStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
}

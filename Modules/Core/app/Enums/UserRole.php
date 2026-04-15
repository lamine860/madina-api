<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Seller = 'seller';
    case Customer = 'customer';
}

<?php

declare(strict_types=1);

namespace Modules\Notification\Services;

use Modules\Core\Models\User;

final class UserPhoneResolver
{
    public function resolve(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $user->loadMissing('customer');

        $phone = $user->customer?->phone;

        if ($phone === null || $phone === '') {
            return null;
        }

        return $phone;
    }
}

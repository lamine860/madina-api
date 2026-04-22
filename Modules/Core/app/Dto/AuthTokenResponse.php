<?php

declare(strict_types=1);

namespace Modules\Core\Dto;

use Modules\Core\Models\User;

final readonly class AuthTokenResponse
{
    public function __construct(
        public User $user,
        public string $plainTextToken,
    ) {}
}

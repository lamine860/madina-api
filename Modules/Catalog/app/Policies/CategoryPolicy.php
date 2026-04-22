<?php

declare(strict_types=1);

namespace Modules\Catalog\Policies;

use Modules\Catalog\Models\Category;
use Modules\Core\Enums\UserRole;
use Modules\Core\Models\User;

final class CategoryPolicy
{
    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, Category $category): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->role === UserRole::Admin;
    }
}

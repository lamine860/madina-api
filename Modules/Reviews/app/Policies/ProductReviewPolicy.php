<?php

declare(strict_types=1);

namespace Modules\Reviews\Policies;

use Modules\Core\Enums\UserRole;
use Modules\Core\Models\User;
use Modules\Reviews\Models\ProductReview;

final class ProductReviewPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, ProductReview $review): bool
    {
        if ($review->is_published) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        if ((int) $review->user_id === (int) $user->id) {
            return true;
        }

        return $user->role === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ProductReview $review): bool
    {
        if ((int) $review->user_id === (int) $user->id) {
            return true;
        }

        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, ProductReview $review): bool
    {
        return $this->update($user, $review);
    }
}

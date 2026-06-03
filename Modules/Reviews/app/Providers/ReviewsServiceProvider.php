<?php

declare(strict_types=1);

namespace Modules\Reviews\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\Reviews\Models\ProductReview;
use Modules\Reviews\Policies\ProductReviewPolicy;
use Modules\Reviews\Services\ReviewEligibilityService;
use Modules\Reviews\Services\ReviewService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ReviewsServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Reviews';

    protected string $nameLower = 'reviews';

    /**
     * @var string[]
     */
    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        $this->app->singleton(ReviewEligibilityService::class);
        $this->app->singleton(ReviewService::class);

        parent::register();
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(ProductReview::class, ProductReviewPolicy::class);
    }
}

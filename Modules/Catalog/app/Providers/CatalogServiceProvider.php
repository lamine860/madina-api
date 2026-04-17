<?php

declare(strict_types=1);

namespace Modules\Catalog\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Policies\ProductPolicy;
use Modules\Catalog\Services\ProductService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class CatalogServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Catalog';

    protected string $nameLower = 'catalog';

    /**
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        $this->app->singleton(ProductService::class);

        parent::register();
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Product::class, ProductPolicy::class);
    }
}

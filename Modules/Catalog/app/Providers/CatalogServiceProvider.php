<?php

declare(strict_types=1);

namespace Modules\Catalog\Providers;

use Illuminate\Support\Facades\Gate;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Policies\CategoryPolicy;
use Modules\Catalog\Policies\ProductPolicy;
use Modules\Catalog\Services\CategoryService;
use Modules\Catalog\Services\ProductImageService;
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
        $this->app->singleton(ImageManager::class, function (): ImageManager {
            $driver = config('catalog.image.driver', Driver::class);

            return new ImageManager($driver);
        });

        $this->app->singleton(CategoryService::class);
        $this->app->singleton(ProductImageService::class);
        $this->app->singleton(ProductService::class);

        parent::register();
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
    }
}

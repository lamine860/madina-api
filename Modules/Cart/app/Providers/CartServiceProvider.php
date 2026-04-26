<?php

declare(strict_types=1);

namespace Modules\Cart\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Models\Wishlist;
use Modules\Cart\Policies\CartItemPolicy;
use Modules\Cart\Policies\WishlistPolicy;
use Modules\Cart\Services\CartService;
use Modules\Cart\Services\WishlistService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class CartServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Cart';

    protected string $nameLower = 'cart';

    /**
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        $this->app->singleton(CartService::class);
        $this->app->singleton(WishlistService::class);

        parent::register();
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(CartItem::class, CartItemPolicy::class);
        Gate::policy(Wishlist::class, WishlistPolicy::class);
    }
}

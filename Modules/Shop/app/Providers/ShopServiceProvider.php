<?php

declare(strict_types=1);

namespace Modules\Shop\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Modules\Shop\Models\Shop;
use Modules\Shop\Policies\ShopPolicy;
use Modules\Shop\Services\ShopService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ShopServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Shop';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'shop';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        $this->app->singleton(ShopService::class);

        parent::register();
    }

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Shop::class, ShopPolicy::class);
    }
}

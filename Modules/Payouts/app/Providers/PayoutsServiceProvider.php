<?php

declare(strict_types=1);

namespace Modules\Payouts\Providers;

use Modules\Payouts\Services\PayoutService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class PayoutsServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Payouts';

    protected string $nameLower = 'payouts';

    /**
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(module_path($this->name, 'config/payouts.php'), 'payouts');

        $this->app->singleton(PayoutService::class);

        parent::register();
    }
}

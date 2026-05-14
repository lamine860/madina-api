<?php

declare(strict_types=1);

namespace Modules\Shipping\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\Shipping\Models\Shipment;
use Modules\Shipping\Policies\ShipmentPolicy;
use Modules\Shipping\Services\CodeGenerator;
use Modules\Shipping\Services\ShippingService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ShippingServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Shipping';

    protected string $nameLower = 'shipping';

    /**
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(module_path($this->name, 'config/shipping.php'), 'shipping');

        $this->app->singleton(CodeGenerator::class);
        $this->app->singleton(ShippingService::class);

        parent::register();
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Shipment::class, ShipmentPolicy::class);
    }
}

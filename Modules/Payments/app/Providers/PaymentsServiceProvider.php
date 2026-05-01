<?php

declare(strict_types=1);

namespace Modules\Payments\Providers;

use Modules\Payments\Services\LengoPayService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class PaymentsServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Payments';

    protected string $nameLower = 'payments';

    /**
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        $this->app->singleton(LengoPayService::class);

        parent::register();
    }

    public function boot(): void
    {
        parent::boot();
    }
}

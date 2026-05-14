<?php

declare(strict_types=1);

namespace Modules\Shipping\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Orders\Events\OrderPaid;
use Modules\Shipping\Listeners\CreateFulfillmentOnOrderPaid;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        OrderPaid::class => [
            CreateFulfillmentOnOrderPaid::class,
        ],
    ];

    protected static $shouldDiscoverEvents = false;

    protected function configureEmailVerification(): void {}
}

<?php

declare(strict_types=1);

namespace Modules\Orders\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Orders\Events\OrderCreated;
use Modules\Orders\Listeners\HandlePaymentConfirmed;
use Modules\Orders\Listeners\SendOrderConfirmation;
use Modules\Payments\Events\PaymentConfirmed;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        OrderCreated::class => [
            SendOrderConfirmation::class,
        ],
        PaymentConfirmed::class => [
            HandlePaymentConfirmed::class,
        ],
    ];

    protected static $shouldDiscoverEvents = false;

    protected function configureEmailVerification(): void {}
}

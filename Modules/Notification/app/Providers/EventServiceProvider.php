<?php

declare(strict_types=1);

namespace Modules\Notification\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Notification\Listeners\SendOrderPaidSms;
use Modules\Notification\Listeners\SendSellerOrderPaidSms;
use Modules\Orders\Events\OrderPaid;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        OrderPaid::class => [
            SendOrderPaidSms::class,
            SendSellerOrderPaidSms::class,
        ],
    ];

    protected static $shouldDiscoverEvents = false;

    protected function configureEmailVerification(): void {}
}

<?php

declare(strict_types=1);

namespace Modules\Payouts\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [];

    protected static $shouldDiscoverEvents = false;

    protected function configureEmailVerification(): void {}
}

<?php

declare(strict_types=1);

namespace Modules\Notification\Providers;

use InvalidArgumentException;
use Modules\Notification\Contracts\SmsProviderInterface;
use Modules\Notification\Services\PhoneNormalizer;
use Modules\Notification\Services\Providers\OrangeSmsProvider;
use Modules\Notification\Services\SmsService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class NotificationServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Notification';

    protected string $nameLower = 'notification';

    /**
     * @var string[]
     */
    protected array $providers = [];

    public function register(): void
    {
        $this->mergeConfigFrom(module_path($this->name, 'config/notification.php'), 'notification');

        $this->app->singleton(PhoneNormalizer::class);
        $this->app->singleton(OrangeSmsProvider::class);
        $this->app->singleton(SmsService::class);

        $this->app->bind(SmsProviderInterface::class, function ($app): SmsProviderInterface {
            return match (config('notification.sms_provider')) {
                'orange' => $app->make(OrangeSmsProvider::class),
                default => throw new InvalidArgumentException('SMS provider not supported.'),
            };
        });

        parent::register();
    }
}

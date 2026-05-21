<?php

declare(strict_types=1);

namespace Modules\Notification\Contracts;

interface SmsProviderInterface
{
    public function send(string $to, string $message): bool;

    public function getBalance(): int;
}

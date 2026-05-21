<?php

declare(strict_types=1);

namespace Modules\Notification\Concerns;

use Modules\Notification\Exceptions\OrangeSmsException;
use Modules\Notification\Services\SmsService;

trait HasSmsNotification
{
    abstract protected function smsPhone(): ?string;

    /**
     * @throws OrangeSmsException
     */
    public function sendSms(string $message, bool $async = true): void
    {
        $phone = $this->smsPhone();

        if ($phone === null || $phone === '') {
            throw new OrangeSmsException('Numéro de téléphone manquant pour l’envoi SMS.');
        }

        app(SmsService::class)->send($phone, $message, $async);
    }
}

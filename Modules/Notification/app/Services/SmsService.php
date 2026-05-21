<?php

declare(strict_types=1);

namespace Modules\Notification\Services;

use Modules\Notification\Contracts\SmsProviderInterface;
use Modules\Notification\Enums\SmsStatus;
use Modules\Notification\Exceptions\OrangeSmsException;
use Modules\Notification\Jobs\SendSmsJob;
use Modules\Notification\Models\SmsLog;
use Throwable;

final class SmsService
{
    public function __construct(
        private readonly SmsProviderInterface $provider,
        private readonly PhoneNormalizer $phoneNormalizer,
    ) {}

    /**
     * @throws OrangeSmsException
     */
    public function send(string $to, string $message, bool $async = true): void
    {
        $recipient = $this->phoneNormalizer->normalize($to);

        $smsLog = SmsLog::query()->create([
            'recipient' => $recipient,
            'message' => $message,
            'status' => SmsStatus::Pending,
            'provider' => (string) config('notification.sms_provider', 'orange'),
        ]);

        if ($async) {
            SendSmsJob::dispatch($smsLog)->onQueue('notifications');

            return;
        }

        try {
            $this->deliver($smsLog);
        } catch (Throwable $e) {
            $smsLog->update([
                'status' => SmsStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);

            throw $e instanceof OrangeSmsException
                ? $e
                : new OrangeSmsException($e->getMessage(), previous: $e);
        }
    }

    /**
     * @throws OrangeSmsException
     */
    public function deliver(SmsLog $smsLog): void
    {
        if ($smsLog->status === SmsStatus::Sent) {
            return;
        }

        $this->provider->send($smsLog->recipient, $smsLog->message);

        $smsLog->update([
            'status' => SmsStatus::Sent,
            'sent_at' => now(),
            'error_message' => null,
        ]);
    }
}

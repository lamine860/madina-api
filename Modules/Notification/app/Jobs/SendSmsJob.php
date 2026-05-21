<?php

declare(strict_types=1);

namespace Modules\Notification\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Notification\Enums\SmsStatus;
use Modules\Notification\Models\SmsLog;
use Modules\Notification\Services\SmsService;
use Throwable;

final class SendSmsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public SmsLog $smsLog,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(SmsService $smsService): void
    {
        $smsLog = $this->smsLog->fresh();

        if ($smsLog === null || $smsLog->status === SmsStatus::Sent) {
            return;
        }

        $smsService->deliver($smsLog);
    }

    public function failed(Throwable $exception): void
    {
        $smsLog = $this->smsLog->fresh();

        if ($smsLog === null || $smsLog->status === SmsStatus::Sent) {
            return;
        }

        $smsLog->update([
            'status' => SmsStatus::Failed,
            'error_message' => $exception->getMessage(),
        ]);
    }
}

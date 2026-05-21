<?php

declare(strict_types=1);

namespace Modules\Notification\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Notification\Enums\SmsStatus;
use Modules\Notification\Models\SmsLog;

/**
 * @extends Factory<SmsLog>
 */
class SmsLogFactory extends Factory
{
    protected $model = SmsLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipient' => '224'.fake()->numerify('#########'),
            'message' => fake()->sentence(),
            'status' => SmsStatus::Pending,
            'provider' => 'orange',
            'error_message' => null,
            'sent_at' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => SmsStatus::Sent,
            'sent_at' => now(),
            'error_message' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => SmsStatus::Failed,
            'error_message' => 'Provider error',
            'sent_at' => null,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Notification\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Notification\Database\Factories\SmsLogFactory;
use Modules\Notification\Enums\SmsStatus;

#[Fillable([
    'recipient',
    'message',
    'status',
    'provider',
    'error_message',
    'sent_at',
])]
class SmsLog extends Model
{
    /** @use HasFactory<SmsLogFactory> */
    use HasFactory;

    protected static function newFactory(): SmsLogFactory
    {
        return SmsLogFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SmsStatus::class,
            'sent_at' => 'datetime',
        ];
    }
}

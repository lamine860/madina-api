<?php

declare(strict_types=1);

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Entities\User;

#[Fillable([
    'user_id',
    'name',
    'slug',
    'description',
    'logo_url',
    'company_name',
    'vat_number',
    'is_verified',
])]
class Shop extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * URL publique complète du logo (chemin stocké en base = relatif au disque `public`).
     */
    public function logoPublicUrl(): ?string
    {
        $path = $this->getRawOriginal('logo_url');

        if ($path === null || $path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}

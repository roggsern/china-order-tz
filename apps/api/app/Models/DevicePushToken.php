<?php

namespace App\Models;

use App\Enums\PushTokenPlatform;
use App\Enums\PushTokenProvider;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\DevicePushTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class DevicePushToken extends Model
{
    /** @use HasFactory<DevicePushTokenFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'user_id',
        'push_token',
        'provider',
        'platform',
        'installation_id',
        'app_version',
        'device_name',
        'is_active',
        'last_seen_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'provider' => PushTokenProvider::class,
            'platform' => PushTokenPlatform::class,
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->is_active && $this->revoked_at === null;
    }

    public function markRevoked(): void
    {
        if (! $this->is_active && $this->revoked_at !== null) {
            return;
        }

        $this->forceFill([
            'is_active' => false,
            'revoked_at' => $this->revoked_at ?? now(),
        ])->save();
    }
}

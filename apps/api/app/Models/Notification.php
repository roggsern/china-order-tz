<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'user_id',
        'customer_id',
        'admin_id',
        'type',
        'event_type',
        'template_key',
        'title',
        'message',
        'channel',
        'status',
        'provider',
        'provider_message_id',
            'data',
        'error_message',
        'sent_at',
        'read_at',
        'idempotency_key',
        'correlation_key',
        'retry_count',
    ];

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'channel' => NotificationChannel::class,
            'status' => NotificationDeliveryStatus::class,
            'data' => 'array',
            'read_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function getIsReadAttribute(): bool
    {
        return $this->read_at !== null
            || $this->status === NotificationDeliveryStatus::Read;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function markAsRead(): void
    {
        if ($this->read_at !== null && $this->status === NotificationDeliveryStatus::Read) {
            return;
        }

        $this->update([
            'read_at' => $this->read_at ?? now(),
            'status' => NotificationDeliveryStatus::Read,
        ]);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null
            || $this->status === NotificationDeliveryStatus::Read;
    }

    protected static function booted(): void
    {
        static::creating(function (Notification $notification): void {
            if ($notification->customer_id === null && $notification->user_id !== null) {
                $notification->customer_id = $notification->user_id;
            }
            if ($notification->user_id === null && $notification->customer_id !== null) {
                $notification->user_id = $notification->customer_id;
            }
            if ($notification->event_type === null && $notification->type !== null) {
                $notification->event_type = $notification->type instanceof NotificationType
                    ? $notification->type->value
                    : (string) $notification->type;
            }
            if ($notification->channel === null) {
                $notification->channel = NotificationChannel::InApp;
            }
            if ($notification->status === null) {
                $notification->status = NotificationDeliveryStatus::Pending;
            }
        });
    }
}

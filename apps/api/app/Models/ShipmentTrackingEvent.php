<?php

namespace App\Models;

use App\Enums\TrackingEventType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\ShipmentTrackingEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only tracking event. Never update or delete after creation.
 */
class ShipmentTrackingEvent extends Model
{
    /** @use HasFactory<ShipmentTrackingEventFactory> */
    use HasFactory, HasUuidPrimaryKey;

    public $timestamps = true;

    protected $fillable = [
        'shipment_id',
        'event_type',
        'description',
        'location',
        'event_at',
        'created_by',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => TrackingEventType::class,
            'event_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Prevent updates — tracking history is append-only.
     */
    protected static function booted(): void
    {
        static::updating(function (): bool {
            throw new \RuntimeException('Shipment tracking events are append-only and cannot be updated.');
        });

        static::deleting(function (): bool {
            throw new \RuntimeException('Shipment tracking events are append-only and cannot be deleted.');
        });
    }
}

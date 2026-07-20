<?php

namespace App\Models;

use App\Enums\ShipmentLifecycleStatus;
use App\Enums\TransportMode;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'order_id',
        'fulfillment_id',
        'shipment_number',
        'transport_mode',
        'status',
        'carrier_name',
        'tracking_reference',
        'origin',
        'destination',
        'booked_at',
        'shipped_at',
        'delivered_at',
        'notes',
        // Legacy aliases
        'carrier',
        'tracking_number',
    ];

    protected function casts(): array
    {
        return [
            'transport_mode' => TransportMode::class,
            'status' => ShipmentLifecycleStatus::class,
            'booked_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(ShipmentTrackingEvent::class)->orderBy('event_at')->orderBy('created_at');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}

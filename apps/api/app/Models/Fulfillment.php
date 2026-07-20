<?php

namespace App\Models;

use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStrategy;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\FulfillmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fulfillment extends Model
{
    /** @use HasFactory<FulfillmentFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'order_id',
        'strategy',
        'status',
        'assigned_to',
        'started_at',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'strategy' => FulfillmentStrategy::class,
            'status' => FulfillmentStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_to');
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    public function warehouseJob(): HasOne
    {
        return $this->hasOne(WarehouseJob::class);
    }
}

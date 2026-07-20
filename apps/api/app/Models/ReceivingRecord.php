<?php

namespace App\Models;

use App\Enums\ReceivingStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\ReceivingRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReceivingRecord extends Model
{
    /** @use HasFactory<ReceivingRecordFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'purchase_order_id',
        'store_id',
        'inventory_location_id',
        'received_by',
        'status',
        'received_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReceivingStatus::class,
            'received_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function inventoryLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function receivedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReceivingRecordItem::class);
    }
}

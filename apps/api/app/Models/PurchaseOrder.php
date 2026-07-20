<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'supplier_id',
        'order_id',
        'fulfillment_id',
        'purchase_number',
        'idempotency_key',
        'status',
        'supplier_response',
        'supplier_response_notes',
        'supplier_responded_at',
        'currency',
        'notes',
        'ordered_at',
        'confirmed_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'supplier_responded_at' => 'datetime',
            'ordered_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receivingRecords(): HasMany
    {
        return $this->hasMany(ReceivingRecord::class);
    }

    public function isFullyReceived(): bool
    {
        $this->loadMissing('items');

        if ($this->items->isEmpty()) {
            return false;
        }

        return $this->items->every(
            fn (PurchaseOrderItem $item) => (int) $item->quantity_received >= (int) $item->quantity_ordered
        );
    }

    public function hasAnyReceived(): bool
    {
        $this->loadMissing('items');

        return $this->items->contains(
            fn (PurchaseOrderItem $item) => (int) $item->quantity_received > 0
        );
    }
}

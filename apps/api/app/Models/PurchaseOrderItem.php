<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\PurchaseOrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    /** @use HasFactory<PurchaseOrderItemFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'purchase_order_id',
        'product_variant_id',
        'quantity_ordered',
        'quantity_received',
        'unit_cost',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'integer',
            'quantity_received' => 'integer',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function receivingItems(): HasMany
    {
        return $this->hasMany(ReceivingRecordItem::class);
    }

    public function quantityOutstanding(): int
    {
        return max(0, (int) $this->quantity_ordered - (int) $this->quantity_received);
    }
}

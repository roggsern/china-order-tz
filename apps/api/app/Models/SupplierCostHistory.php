<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierCostHistory extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'supplier_cost_histories';

    protected $fillable = [
        'supplier_id',
        'product_variant_id',
        'purchase_order_item_id',
        'purchase_cost',
        'currency',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'purchase_cost' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }
}

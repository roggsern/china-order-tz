<?php

namespace App\Models;

use App\Enums\WarehousePickListLineStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehousePickListLine extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'pick_list_id',
        'order_item_id',
        'product_variant_id',
        'product_name',
        'sku',
        'quantity',
        'picked_quantity',
        'warehouse_bin_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => WarehousePickListLineStatus::class,
            'quantity' => 'integer',
            'picked_quantity' => 'integer',
        ];
    }

    public function pickList(): BelongsTo
    {
        return $this->belongsTo(WarehousePickList::class, 'pick_list_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouseBin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class, 'warehouse_bin_id');
    }
}

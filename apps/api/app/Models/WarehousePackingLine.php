<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehousePackingLine extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'packing_record_id',
        'order_item_id',
        'quantity',
        'packed_quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'packed_quantity' => 'integer',
        ];
    }

    public function packingRecord(): BelongsTo
    {
        return $this->belongsTo(WarehousePackingRecord::class, 'packing_record_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivingRecordItem extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'receiving_record_id',
        'purchase_order_item_id',
        'quantity_received',
    ];

    protected function casts(): array
    {
        return [
            'quantity_received' => 'integer',
        ];
    }

    public function receivingRecord(): BelongsTo
    {
        return $this->belongsTo(ReceivingRecord::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }
}

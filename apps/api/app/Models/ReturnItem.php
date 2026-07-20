<?php

namespace App\Models;

use App\Enums\InventoryDisposition;
use App\Enums\ReturnItemResolution;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\ReturnItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnItem extends Model
{
    /** @use HasFactory<ReturnItemFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'return_request_id',
        'order_item_id',
        'quantity',
        'reason',
        'condition',
        'inventory_disposition',
        'resolution',
        'refund_amount',
        'replacement_requested',
        'exchange_variant_id',
        'exchange_unit_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'resolution' => ReturnItemResolution::class,
            'inventory_disposition' => InventoryDisposition::class,
            'refund_amount' => 'decimal:2',
            'exchange_unit_price' => 'decimal:2',
            'replacement_requested' => 'boolean',
        ];
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function exchangeVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'exchange_variant_id');
    }
}

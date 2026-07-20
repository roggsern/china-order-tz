<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable per-line cost snapshot captured at order creation.
 */
class OrderCostSnapshot extends Model
{
    use HasUuidPrimaryKey;

    public $timestamps = false;

    protected $fillable = [
        'order_item_id',
        'supplier_cost',
        'shipping_cost',
        'other_cost',
        'total_cost',
        'currency',
        'exchange_rate',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'supplier_cost' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'other_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'exchange_rate' => 'decimal:8',
            'created_at' => 'datetime',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDiscountSnapshot extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'order_id',
        'order_item_id',
        'promotion_id',
        'promotion_name',
        'promotion_code',
        'original_amount',
        'discount_amount',
        'final_amount',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}

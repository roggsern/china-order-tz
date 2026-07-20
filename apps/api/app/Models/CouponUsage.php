<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponUsage extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'coupon_id',
        'user_id',
        'order_id',
        'discount_amount',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'discount_amount' => 'decimal:2',
            'used_at' => 'datetime',
        ];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerMetric extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'customer_profile_id',
        'total_orders',
        'completed_orders',
        'cancelled_orders',
        'total_spend',
        'total_refunds',
        'gross_profit_generated',
        'average_order_value',
        'first_order_at',
        'last_order_at',
        'last_payment_at',
        'last_activity_at',
        'currency',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'total_orders' => 'integer',
            'completed_orders' => 'integer',
            'cancelled_orders' => 'integer',
            'total_spend' => 'decimal:2',
            'total_refunds' => 'decimal:2',
            'gross_profit_generated' => 'decimal:2',
            'average_order_value' => 'decimal:2',
            'first_order_at' => 'datetime',
            'last_order_at' => 'datetime',
            'last_payment_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'calculated_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class, 'customer_profile_id');
    }
}

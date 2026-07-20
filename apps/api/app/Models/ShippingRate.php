<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingRate extends Model
{
    use HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'shipping_method_id',
        'base_cost',
        'cost_per_kg',
        'min_weight',
        'max_weight',
        'estimated_delivery_days',
        'currency',
        'is_active',
        'effective_from',
        'effective_until',
    ];

    protected function casts(): array
    {
        return [
            'base_cost' => 'decimal:2',
            'cost_per_kg' => 'decimal:2',
            'min_weight' => 'decimal:3',
            'max_weight' => 'decimal:3',
            'estimated_delivery_days' => 'integer',
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
        ];
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }
}

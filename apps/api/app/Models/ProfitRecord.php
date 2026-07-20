<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Order-level profit record calculated from immutable cost snapshots + revenue.
 */
class ProfitRecord extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'order_id',
        'revenue',
        'total_cost',
        'gross_profit',
        'margin_percentage',
        'currency',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'revenue' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'gross_profit' => 'decimal:2',
            'margin_percentage' => 'decimal:4',
            'calculated_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

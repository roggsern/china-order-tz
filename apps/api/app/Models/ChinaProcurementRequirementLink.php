<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChinaProcurementRequirementLink extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'requirement_id',
        'order_id',
        'order_item_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(ChinaProcurementRequirement::class, 'requirement_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}

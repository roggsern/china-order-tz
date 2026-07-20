<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCountLine extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'inventory_count_session_id', 'product_variant_id', 'variant_inventory_id',
        'system_quantity', 'counted_quantity', 'difference', 'reason', 'is_adjusted',
    ];

    protected function casts(): array
    {
        return [
            'system_quantity' => 'integer',
            'counted_quantity' => 'integer',
            'difference' => 'integer',
            'is_adjusted' => 'boolean',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(InventoryCountSession::class, 'inventory_count_session_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(VariantInventory::class, 'variant_inventory_id');
    }
}

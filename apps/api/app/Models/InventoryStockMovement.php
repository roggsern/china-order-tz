<?php

namespace App\Models;

use App\Enums\InventoryMovementType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only VariantInventory movement ledger.
 */
class InventoryStockMovement extends Model
{
    use HasUuidPrimaryKey;

    public $timestamps = false;

    protected $fillable = [
        'variant_inventory_id', 'product_variant_id', 'inventory_location_id', 'store_id',
        'movement_type', 'quantity_before', 'quantity_change', 'quantity_after',
        'damaged_after', 'inspection_after', 'reason', 'reference_type', 'reference_id',
        'actor_type', 'actor_id', 'metadata', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'movement_type' => InventoryMovementType::class,
            'quantity_before' => 'integer',
            'quantity_change' => 'integer',
            'quantity_after' => 'integer',
            'damaged_after' => 'integer',
            'inspection_after' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(VariantInventory::class, 'variant_inventory_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}

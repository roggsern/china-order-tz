<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\VariantInventoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VariantInventory extends Model
{
    /** @use HasFactory<VariantInventoryFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'variant_inventories';

    protected $fillable = [
        'product_variant_id',
        'inventory_location_id',
        'warehouse_code',
        'on_hand',
        'reserved',
        'damaged',
        'inspection',
        'reorder_level',
        'safety_stock',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'on_hand' => 'integer',
            'reserved' => 'integer',
            'damaged' => 'integer',
            'inspection' => 'integer',
            'available' => 'integer',
            'reorder_level' => 'integer',
            'safety_stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (VariantInventory $inventory) {
            // Keep available in sync for drivers without generated columns (sqlite).
            if (! $inventory->isGeneratedAvailableColumn()) {
                $inventory->attributes['available'] = max(
                    0,
                    (int) $inventory->on_hand - (int) $inventory->reserved,
                );
            }
        });
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function inventoryLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function available(): int
    {
        if (array_key_exists('available', $this->attributes) && $this->attributes['available'] !== null) {
            return (int) $this->attributes['available'];
        }

        return max(0, (int) $this->on_hand - (int) $this->reserved);
    }

    public function needsReorder(): bool
    {
        return $this->available() <= (int) $this->reorder_level;
    }

    /** Physical units including non-sellable buckets. */
    public function physicalQuantity(): int
    {
        return (int) $this->on_hand + (int) $this->damaged + (int) $this->inspection;
    }

    public function movements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InventoryStockMovement::class, 'variant_inventory_id');
    }

    public function isGeneratedAvailableColumn(): bool
    {
        $driver = $this->getConnection()->getDriverName();

        return in_array($driver, ['mysql', 'pgsql'], true);
    }
}

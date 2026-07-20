<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\SupplierProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProduct extends Model
{
    /** @use HasFactory<SupplierProductFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'supplier_id',
        'product_variant_id',
        'supplier_sku',
        'purchase_cost',
        'currency',
        'lead_time_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'purchase_cost' => 'decimal:2',
            'lead_time_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}

<?php

namespace App\Models;

use App\Enums\ChinaProcurementRequirementStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChinaProcurementRequirement extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'supplier_id',
        'quantity_required',
        'quantity_purchased',
        'status',
        'variant_attributes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_required' => 'integer',
            'quantity_purchased' => 'integer',
            'status' => ChinaProcurementRequirementStatus::class,
            'variant_attributes' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(ChinaProcurementRequirementLink::class, 'requirement_id');
    }

    public function remainingQuantity(): int
    {
        return max(0, (int) $this->quantity_required - (int) $this->quantity_purchased);
    }
}

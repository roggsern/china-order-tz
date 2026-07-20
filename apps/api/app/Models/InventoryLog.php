<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryLog extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'quantity_change',
        'quantity_after',
        'reason',
        'reference_type',
        'reference_id',
        'performed_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity_change' => 'integer',
            'quantity_after' => 'integer',
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

    public function performedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'performed_by_admin_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}

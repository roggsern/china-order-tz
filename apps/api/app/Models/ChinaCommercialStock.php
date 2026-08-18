<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Concerns\InvalidatesChinaStorefrontDiscovery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChinaCommercialStock extends Model
{
    use HasUuidPrimaryKey, InvalidatesChinaStorefrontDiscovery;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'available_quantity',
        'reserved_quantity',
        'ordered_quantity',
    ];

    protected function casts(): array
    {
        return [
            'available_quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'ordered_quantity' => 'integer',
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
}

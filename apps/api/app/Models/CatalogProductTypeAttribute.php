<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CatalogProductTypeAttribute extends Pivot
{
    use HasUuidPrimaryKey;

    protected $table = 'catalog_product_type_attributes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'catalog_product_type_id',
        'catalog_attribute_id',
        'is_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function catalogProductType(): BelongsTo
    {
        return $this->belongsTo(CatalogProductType::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(CatalogAttribute::class, 'catalog_attribute_id');
    }
}

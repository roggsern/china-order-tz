<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductTypeAttribute extends Pivot
{
    use HasUuidPrimaryKey;

    public $incrementing = false;

    protected $table = 'product_type_attributes';

    protected $fillable = [
        'product_type_id',
        'product_attribute_id',
        'sort_order',
        'is_required',
        'participates_in_configuration',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_required' => 'boolean',
            'participates_in_configuration' => 'boolean',
        ];
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class, 'product_attribute_id');
    }
}

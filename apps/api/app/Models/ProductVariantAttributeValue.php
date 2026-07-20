<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\ProductVariantAttributeValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariantAttributeValue extends Model
{
    /** @use HasFactory<ProductVariantAttributeValueFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $table = 'product_variant_attribute_values';

    protected $fillable = [
        'product_variant_id',
        'catalog_attribute_id',
        'option_id',
        'value_text',
        'value_number',
        'value_boolean',
    ];

    protected function casts(): array
    {
        return [
            'value_number' => 'decimal:4',
            'value_boolean' => 'boolean',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(CatalogAttribute::class, 'catalog_attribute_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(CatalogAttributeOption::class, 'option_id');
    }
}

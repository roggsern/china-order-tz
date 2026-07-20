<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\CatalogProductAttributeValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CatalogProductAttributeValue extends Model
{
    /** @use HasFactory<CatalogProductAttributeValueFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'catalog_product_attribute_values';

    protected $fillable = [
        'product_id',
        'catalog_attribute_id',
        'value_text',
        'value_number',
        'value_boolean',
        'option_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value_number' => 'decimal:4',
            'value_boolean' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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

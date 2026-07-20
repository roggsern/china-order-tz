<?php

namespace App\Models;

use App\Enums\AttributeType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\ProductAttributeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class ProductAttribute extends Model
{
    /** @use HasFactory<ProductAttributeFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'unit',
        'validation',
        'is_filterable',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => AttributeType::class,
            'validation' => 'array',
            'is_filterable' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class)->orderBy('sort_order');
    }

    public function productTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductType::class,
            'product_type_attributes',
            'product_attribute_id',
            'product_type_id'
        )
            ->using(ProductTypeAttribute::class)
            ->withPivot(['id', 'sort_order', 'is_required', 'participates_in_configuration'])
            ->withTimestamps();
    }
}

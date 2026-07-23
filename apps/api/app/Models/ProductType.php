<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Configuration Template domain (ADR 052).
 *
 * Legacy technical name: ProductType / table product_types.
 * Purpose: configuration schema, SKU patterns, attribute bindings, dependency
 * rules, and configuration generation — NOT a catalog navigation taxonomy level.
 *
 * Catalog taxonomy leaf is CatalogProductType (catalog_product_types).
 */
class ProductType extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'sku_pattern',
        'has_configurations',
        'allows_price_override',
        'allows_moq_pricing',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'has_configurations' => 'boolean',
            'allows_price_override' => 'boolean',
            'allows_moq_pricing' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function typeAttributes(): HasMany
    {
        return $this->hasMany(ProductTypeAttribute::class)->orderBy('sort_order');
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductAttribute::class,
            'product_type_attributes',
            'product_type_id',
            'product_attribute_id'
        )
            ->using(ProductTypeAttribute::class)
            ->withPivot(['id', 'sort_order', 'is_required', 'participates_in_configuration'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function attributeDependencies(): HasMany
    {
        return $this->hasMany(AttributeDependency::class);
    }
}

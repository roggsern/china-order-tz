<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\CatalogProductTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catalog Product Type — taxonomy leaf under a leaf category (ADR 052 / Phase 1D-B).
 *
 * Hierarchy: Department → Category hierarchy → Leaf Category → CatalogProductType → Product.
 * Distinct from configuration-schema ProductType (Configuration Template).
 */
class CatalogProductType extends Model
{
    /** @use HasFactory<CatalogProductTypeFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'catalog_product_types';

    protected $fillable = [
        'subcategory_id',
        'name',
        'slug',
        'image',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Parent leaf category (root without children, or nested category without children).
     */
    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'subcategory_id');
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(
            CatalogAttribute::class,
            'catalog_product_type_attributes',
            'catalog_product_type_id',
            'catalog_attribute_id',
        )
            ->using(CatalogProductTypeAttribute::class)
            ->withPivot(['id', 'is_required', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'catalog_product_type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

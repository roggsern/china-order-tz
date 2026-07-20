<?php

namespace App\Models;

use App\Enums\CatalogAttributeType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\CatalogAttributeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CatalogAttribute extends Model
{
    /** @use HasFactory<CatalogAttributeFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'catalog_attributes';

    protected $fillable = [
        'name',
        'slug',
        'type',
        'unit',
        'is_filterable',
        'is_required',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CatalogAttributeType::class,
            'is_filterable' => 'boolean',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(CatalogAttributeOption::class, 'catalog_attribute_id')
            ->orderBy('sort_order')
            ->orderBy('value');
    }

    public function catalogProductTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            CatalogProductType::class,
            'catalog_product_type_attributes',
            'catalog_attribute_id',
            'catalog_product_type_id',
        )
            ->using(CatalogProductTypeAttribute::class)
            ->withPivot(['id', 'is_required', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function productValues(): HasMany
    {
        return $this->hasMany(CatalogProductAttributeValue::class, 'catalog_attribute_id');
    }

    public function variantValues(): HasMany
    {
        return $this->hasMany(ProductVariantAttributeValue::class, 'catalog_attribute_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFilterable($query)
    {
        return $query->where('is_filterable', true)->where('is_active', true);
    }
}

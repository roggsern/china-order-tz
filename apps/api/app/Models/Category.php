<?php

namespace App\Models;

use App\Enums\CatalogOrigin;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'department_id',
        'store_id',
        'parent_id',
        'origin',
        'product_type_id',
        'name',
        'slug',
        'description',
        'image',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'origin' => CatalogOrigin::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * All direct children (including inactive) — admin / maintenance use.
     */
    public function childrenAll(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * Recursive children for unlimited nesting (adjacency list).
     */
    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeSubcategories($query)
    {
        return $query->whereNotNull('parent_id');
    }

    /**
     * Direct child categories (subcategories), including inactive — admin use.
     */
    public function subcategories(): HasMany
    {
        return $this->childrenAll();
    }

    public function catalogProductTypes(): HasMany
    {
        return $this->hasMany(CatalogProductType::class, 'subcategory_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function catalogProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'category_product')
            ->withTimestamps();
    }

    /**
     * Brands associated with this category node (many-to-many; not exclusive ownership).
     */
    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'brand_category')
            ->withTimestamps();
    }

    /**
     * Resolve origin from this node or walk up the parent chain.
     */
    public function resolvedOrigin(): ?CatalogOrigin
    {
        $current = $this;

        while ($current !== null) {
            if ($current->origin !== null) {
                return $current->origin instanceof CatalogOrigin
                    ? $current->origin
                    : CatalogOrigin::tryFrom((string) $current->origin);
            }

            $current->loadMissing('parent');
            $current = $current->parent;
        }

        return null;
    }
}

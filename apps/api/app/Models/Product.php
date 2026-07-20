<?php

namespace App\Models;

use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'commerce_channel_id',
        'store_id',
        'category_id',
        'brand_id',
        'supplier_id',
        'product_type_id',
        'catalog_product_type_id',
        'fulfillment_source',
        'name',
        'slug',
        'sku',
        'description',
        'short_description',
        'price',
        'air_shipping_price',
        'sea_shipping_price',
        'compare_at_price',
        'cost_price',
        'weight',
        'dimensions',
        'is_active',
        'is_featured',
        'is_demo',
        'lifecycle_status',
        'visibility',
        'sort_order',
        'meta_title',
        'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'air_shipping_price' => 'decimal:2',
            'sea_shipping_price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'weight' => 'decimal:3',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_demo' => 'boolean',
            'lifecycle_status' => ProductLifecycleStatus::class,
            'visibility' => ProductVisibility::class,
            'sort_order' => 'integer',
        ];
    }

    public function commerceChannel(): BelongsTo
    {
        return $this->belongsTo(CommerceChannel::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_product')
            ->withTimestamps();
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    /**
     * Taxonomy catalog product type (Department → Category → Subcategory → CatalogProductType).
     * Distinct from configuration-schema productType().
     */
    public function catalogProductType(): BelongsTo
    {
        return $this->belongsTo(CatalogProductType::class, 'catalog_product_type_id');
    }

    /**
     * Legacy commerce product_images rows (kept for storefront/cart compatibility).
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    /**
     * Catalog specification values (TASK 009).
     * Distinct from config-engine ProductAttributeValue rows.
     */
    public function catalogAttributeValues(): HasMany
    {
        return $this->hasMany(CatalogProductAttributeValue::class);
    }

    /**
     * Catalog Product Media module (images + videos).
     */
    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->ordered();
    }

    public function mediaImages(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->images()->ordered();
    }

    public function videos(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->videos()->ordered();
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** Sellable configurations (Phase A: same rows as variants). */
    public function configurations(): HasMany
    {
        return $this->hasMany(ProductConfiguration::class, 'product_id');
    }

    public function priceTiers(): HasMany
    {
        return $this->hasMany(ConfigurationPriceTier::class);
    }

    public function shippingOptions(): HasMany
    {
        return $this->hasMany(ProductShippingOption::class);
    }

    public function attributeDependencies(): HasMany
    {
        return $this->hasMany(AttributeDependency::class);
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    public function embeddings(): HasMany
    {
        return $this->hasMany(ProductEmbedding::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('lifecycle_status', ProductLifecycleStatus::Draft);
    }

    /**
     * Published for storefront listing: active status + public visibility + active flag.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('lifecycle_status', ProductLifecycleStatus::Active)
            ->where('visibility', ProductVisibility::Public)
            ->where('is_active', true);
    }

    public function scopePurchasable(Builder $query): Builder
    {
        return $query
            ->where('lifecycle_status', ProductLifecycleStatus::Active)
            ->where('is_active', true);
    }

    public function scopeReal(Builder $query): Builder
    {
        return $query->where('is_demo', false);
    }

    public function isPurchasable(): bool
    {
        return $this->lifecycle_status?->isPurchasable() ?? $this->is_active;
    }

    /**
     * Legacy primary image from product_images (storefront/cart compatibility).
     */
    public function primaryImage(): ?ProductImage
    {
        return $this->images()->where('is_primary', true)->first()
            ?? $this->images()->orderBy('sort_order')->first();
    }

    /**
     * Catalog Product Media primary image (TASK 008).
     */
    public function primaryMedia(): ?ProductMedia
    {
        return $this->media()
            ->images()
            ->active()
            ->where('is_primary', true)
            ->first()
            ?? $this->media()->images()->active()->ordered()->first();
    }

    public function isFromChina(): bool
    {
        $this->loadMissing('supplier');

        return strcasecmp($this->supplier?->country ?? '', 'China') === 0;
    }

    /**
     * Whether checkout must collect air/sea shipping for this product.
     * Matches storefront: Air/Sea when freight options exist, or China-sourced stock.
     */
    public function requiresChinaShipping(): bool
    {
        if ($this->isFromChina()) {
            return true;
        }

        if ($this->relationLoaded('shippingOptions')) {
            if ($this->shippingOptions->contains(fn (ProductShippingOption $o) => $o->is_available)) {
                return true;
            }
        } elseif ($this->shippingOptions()->available()->exists()) {
            return true;
        }

        return $this->air_shipping_price !== null || $this->sea_shipping_price !== null;
    }

    public function shippingPriceForMethod(string $method): ?string
    {
        $option = null;

        if ($this->relationLoaded('shippingOptions')) {
            $option = $this->shippingOptions->first(
                function (ProductShippingOption $o) use ($method): bool {
                    $mode = $o->transport_mode instanceof \App\Enums\ShippingMethod
                        ? $o->transport_mode->value
                        : (string) $o->transport_mode;

                    return $o->is_available && $mode === $method;
                }
            );
        } else {
            $option = $this->shippingOptions()
                ->available()
                ->where('transport_mode', $method)
                ->first();
        }

        if ($option !== null) {
            return (string) $option->price;
        }

        return match ($method) {
            'air' => $this->air_shipping_price !== null ? (string) $this->air_shipping_price : null,
            'sea' => $this->sea_shipping_price !== null ? (string) $this->sea_shipping_price : null,
            default => null,
        };
    }
}

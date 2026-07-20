<?php

namespace App\Models;

use App\Enums\VariantPriceType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'product_id',
        'sku',
        'name',
        'price',
        'compare_at_price',
        'cost_price',
        'barcode',
        'weight',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'weight' => 'decimal:3',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Config-engine attribute values (legacy pivot). */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductAttributeValue::class,
            'product_variant_attribute_value',
            'product_variant_id',
            'product_attribute_value_id'
        );
    }

    /** Catalog-driven variant attribute selections (Variants Engine). */
    public function catalogAttributeValues(): HasMany
    {
        return $this->hasMany(ProductVariantAttributeValue::class, 'product_variant_id');
    }

    /** Legacy commerce inventory (config engine). */
    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }

    /** Inventory Engine — stock rows per warehouse. */
    public function inventories(): HasMany
    {
        return $this->hasMany(VariantInventory::class, 'product_variant_id');
    }

    public function priceTiers(): HasMany
    {
        return $this->hasMany(ConfigurationPriceTier::class, 'product_variant_id');
    }

    /** Pricing Engine — list prices (retail/wholesale/dealer/vip × currency). */
    public function prices(): HasMany
    {
        return $this->hasMany(VariantPrice::class, 'product_variant_id');
    }

    public function mainInventory(): ?VariantInventory
    {
        return $this->inventories()
            ->where('warehouse_code', 'MAIN')
            ->where('is_active', true)
            ->first();
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

    /**
     * Currently scheduled & active prices from the Pricing Engine.
     *
     * @return Collection<int, VariantPrice>
     */
    public function activePrices(?string $currency = null): Collection
    {
        $query = $this->prices()->active()->orderBy('price_type')->orderBy('currency');

        if ($currency !== null) {
            $query->currency($currency);
        }

        return $query->get();
    }

    public function retailPrice(?string $currency = 'TZS'): ?VariantPrice
    {
        return $this->prices()
            ->active()
            ->ofType(VariantPriceType::Retail)
            ->when($currency !== null, fn ($query) => $query->currency($currency))
            ->orderBy('minimum_quantity')
            ->first();
    }

    public function wholesalePrice(?string $currency = 'TZS'): ?VariantPrice
    {
        return $this->prices()
            ->active()
            ->ofType(VariantPriceType::Wholesale)
            ->when($currency !== null, fn ($query) => $query->currency($currency))
            ->orderBy('minimum_quantity')
            ->first();
    }

    /** Legacy fallback — prefer VariantPrice via retailPrice(). */
    public function effectivePrice(): string
    {
        $retail = $this->retailPrice();
        if ($retail !== null) {
            return (string) $retail->amount;
        }

        return (string) ($this->price ?? $this->product->price);
    }
}

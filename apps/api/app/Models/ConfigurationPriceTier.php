<?php

namespace App\Models;

use App\Enums\PriceTierType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\ConfigurationPriceTierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quantity / MOQ price tier for a product or configuration.
 * product_variant_id is the configuration id (Phase A physical table: product_variants).
 */
class ConfigurationPriceTier extends Model
{
    /** @use HasFactory<ConfigurationPriceTierFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $table = 'configuration_price_tiers';

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'min_quantity',
        'tier_type',
        'unit_price',
        'discount_percent',
    ];

    protected function casts(): array
    {
        return [
            'min_quantity' => 'integer',
            'tier_type' => PriceTierType::class,
            'unit_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Sellable configuration (stored in product_variants in Phase A). */
    public function configuration(): BelongsTo
    {
        return $this->belongsTo(ProductConfiguration::class, 'product_variant_id');
    }

    /**
     * Resolve the effective unit price for a tier against the current pipeline price.
     */
    public function resolveUnitPrice(string $baseUnitPrice): string
    {
        $tierType = $this->tier_type ?? PriceTierType::FixedUnit;

        if ($tierType === PriceTierType::PercentOff) {
            $percent = max(0, min(100, (float) ($this->discount_percent ?? 0)));
            $base = (float) $baseUnitPrice;
            $discounted = $base * (1 - ($percent / 100));

            return number_format(max(0, $discounted), 2, '.', '');
        }

        return number_format((float) $this->unit_price, 2, '.', '');
    }
}

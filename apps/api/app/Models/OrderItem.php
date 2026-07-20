<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    /**
     * Commercial snapshot fields — immutable after checkout.
     *
     * @var list<string>
     */
    public const IMMUTABLE_SNAPSHOT_FIELDS = [
        'product_name_snapshot',
        'product_slug_snapshot',
        'sku_snapshot',
        'brand_name_snapshot',
        'variant_name_snapshot',
        'variant_sku_snapshot',
        'currency_snapshot',
        'unit_price_snapshot',
        'shipping_mode_snapshot',
        'shipping_price_snapshot',
        'shipping_notes_snapshot',
        'attributes_snapshot',
        'product_image_snapshot',
        'image_snapshot',
        'product_name',
        'variant_name',
        'sku',
        'unit_price',
        'line_total',
        'total_price',
        'currency',
        'shipping_method',
        'shipping_price',
        'shipping_subtotal',
    ];

    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'product_name_snapshot',
        'product_slug_snapshot',
        'sku_snapshot',
        'brand_name_snapshot',
        'variant_name_snapshot',
        'variant_sku_snapshot',
        'currency_snapshot',
        'unit_price_snapshot',
        'shipping_mode_snapshot',
        'shipping_price_snapshot',
        'shipping_notes_snapshot',
        'attributes_snapshot',
        'product_image_snapshot',
        'image_snapshot',
        'product_name',
        'variant_name',
        'sku',
        'quantity',
        'unit_price',
        'line_total',
        'total_price',
        'currency',
        'shipping_method',
        'shipping_price',
        'shipping_subtotal',
        'shipping_method_id',
        'shipping_cost',
        'estimated_delivery_days',
        'shipment_id',
        'delivery_status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'unit_price_snapshot' => 'decimal:2',
            'line_total' => 'decimal:2',
            'total_price' => 'decimal:2',
            'shipping_price' => 'decimal:2',
            'shipping_price_snapshot' => 'decimal:2',
            'shipping_subtotal' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'estimated_delivery_days' => 'integer',
            'attributes_snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (OrderItem $item): void {
            foreach (self::IMMUTABLE_SNAPSHOT_FIELDS as $field) {
                if ($item->isDirty($field)) {
                    throw ValidationException::withMessages([
                        $field => ['Order item snapshot fields are immutable after checkout.'],
                    ]);
                }
            }
        });
    }

    public function getProductNameSnapshotAttribute(?string $value): ?string
    {
        return $value ?: ($this->attributes['product_name'] ?? null);
    }

    public function getVariantNameSnapshotAttribute(?string $value): ?string
    {
        return $value ?: ($this->attributes['variant_name'] ?? null);
    }

    public function getSkuSnapshotAttribute(?string $value): ?string
    {
        return $value ?: ($this->attributes['sku'] ?? null);
    }

    public function getUnitPriceSnapshotAttribute(mixed $value): ?string
    {
        if ($value !== null && $value !== '') {
            return (string) $value;
        }

        return isset($this->attributes['unit_price'])
            ? (string) $this->attributes['unit_price']
            : null;
    }

    public function getCurrencySnapshotAttribute(?string $value): ?string
    {
        return $value ?: ($this->attributes['currency'] ?? null);
    }

    public function getShippingModeSnapshotAttribute(?string $value): ?string
    {
        return $value ?: ($this->attributes['shipping_method'] ?? null);
    }

    public function getShippingPriceSnapshotAttribute(mixed $value): ?string
    {
        if ($value !== null && $value !== '') {
            return (string) $value;
        }

        return isset($this->attributes['shipping_price'])
            ? (string) $this->attributes['shipping_price']
            : null;
    }

    public function getProductImageSnapshotAttribute(?string $value): ?string
    {
        return $value ?: ($this->attributes['image_snapshot'] ?? null);
    }

    public function getLineTotalAttribute(?string $value): ?string
    {
        // Custom accessor bypasses decimal:2 cast — normalize money to 2dp for API consistency.
        if ($value !== null && $value !== '') {
            return number_format((float) $value, 2, '.', '');
        }

        return isset($this->attributes['total_price'])
            ? number_format((float) $this->attributes['total_price'], 2, '.', '')
            : null;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function shippingMethodRecord(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class, 'shipping_method_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function costSnapshot(): HasOne
    {
        return $this->hasOne(OrderCostSnapshot::class);
    }
}

<?php

namespace App\Models;

use App\Enums\ShippingMethod;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'cart_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'unit_price',
        'price_snapshot',
        'currency',
        'shipping_method',
        'shipping_price',
        'shipping_method_id',
        'shipping_cost',
        'estimated_delivery_days',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'price_snapshot' => 'decimal:2',
            'shipping_price' => 'decimal:2',
            'shipping_method' => ShippingMethod::class,
            'shipping_cost' => 'decimal:2',
            'estimated_delivery_days' => 'integer',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
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
        return $this->belongsTo(\App\Models\ShippingMethod::class, 'shipping_method_id');
    }

    public function subtotal(): string
    {
        $price = (string) ($this->price_snapshot ?? $this->unit_price);

        return bcmul($price, (string) $this->quantity, 2);
    }

    public function lineTotal(): string
    {
        return $this->subtotal();
    }

    public function shippingSubtotal(): ?string
    {
        if ($this->shipping_price === null) {
            return null;
        }

        return bcmul((string) $this->shipping_price, (string) $this->quantity, 2);
    }
}

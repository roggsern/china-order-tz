<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\SalesOrigin;
use App\Enums\ShipmentStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'user_id',
        'store_id',
        'sales_origin',
        'commerce_channel_id',
        'commerce_channel_snapshot',
        'checkout_session_id',
        'pos_session_id',
        'coupon_id',
        'order_number',
        'status',
        'shipment_status',
        'shipment_status_updated_at',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'shipping_amount',
        'total',
        'currency',
        'is_demo',
        'notes',
        'placed_at',
        'paid_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'sales_origin' => SalesOrigin::class,
            'shipment_status' => ShipmentStatus::class,
            'shipment_status_updated_at' => 'datetime',
            'commerce_channel_snapshot' => 'array',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'is_demo' => 'boolean',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function commerceChannel(): BelongsTo
    {
        return $this->belongsTo(CommerceChannel::class);
    }

    public function checkoutSession(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class);
    }

    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'pos_session_id');
    }

    public function posReceipt(): HasOne
    {
        return $this->hasOne(PosReceipt::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** Order Engine alias — maps to discount_amount. */
    public function getDiscountTotalAttribute(): string
    {
        return (string) ($this->discount_amount ?? '0.00');
    }

    /** Order Engine alias — maps to tax_amount. */
    public function getTaxTotalAttribute(): string
    {
        return (string) ($this->tax_amount ?? '0.00');
    }

    /** Order Engine alias — maps to shipping_amount. */
    public function getShippingTotalAttribute(): string
    {
        return (string) ($this->shipping_amount ?? '0.00');
    }

    /** Order Engine alias — maps to total. */
    public function getGrandTotalAttribute(): string
    {
        return (string) ($this->total ?? '0.00');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function profitRecord(): HasOne
    {
        return $this->hasOne(ProfitRecord::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function shippingAddress(): HasOne
    {
        return $this->hasOne(ShippingAddress::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function shipmentStatusHistories(): HasMany
    {
        return $this->hasMany(ShipmentStatusHistory::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(OrderTrackingEvent::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function refundTransactions(): HasMany
    {
        return $this->hasMany(RefundTransaction::class);
    }

    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }

    public function fulfillment(): HasOne
    {
        return $this->hasOne(Fulfillment::class);
    }

    public function warehouseJob(): HasOne
    {
        return $this->hasOne(WarehouseJob::class);
    }

    public function deliveryOption(): HasOne
    {
        return $this->hasOne(DeliveryOption::class);
    }

    public function resolveSource(): string
    {
        $this->loadMissing(['items.product.commerceChannel']);

        $needsSupplierFallback = false;

        foreach ($this->items as $item) {
            $product = $item->product;
            if ($product === null) {
                continue;
            }

            if ($product->relationLoaded('commerceChannel') && $product->commerceChannel !== null) {
                if (\App\Enums\CommerceChannelCode::tryFrom($product->commerceChannel->code)
                    === \App\Enums\CommerceChannelCode::ChinaImport) {
                    return 'China';
                }

                continue;
            }

            if (filled($product->fulfillment_source)) {
                if (\App\Enums\CommerceChannelCode::fromFulfillmentSource($product->fulfillment_source)
                    === \App\Enums\CommerceChannelCode::ChinaImport) {
                    return 'China';
                }

                continue;
            }

            $needsSupplierFallback = true;
        }

        if ($needsSupplierFallback) {
            $this->loadMissing(['items.product.supplier']);

            foreach ($this->items as $item) {
                $product = $item->product;
                if ($product === null) {
                    continue;
                }

                if (($product->relationLoaded('commerceChannel') && $product->commerceChannel !== null)
                    || filled($product->fulfillment_source)) {
                    continue;
                }

                if (strcasecmp($product->supplier?->country ?? '', 'China') === 0) {
                    return 'China';
                }
            }
        }

        return 'Dar';
    }

    public function scopeReal(Builder $query): Builder
    {
        return $query->where('is_demo', false);
    }

  /**
   * Allow customer routes to resolve orders by UUID or public order number (e.g. COT-000001).
   */
    public function resolveRouteBinding($value, $field = null): ?static
    {
        if ($field !== null) {
            /** @var static|null $resolved */
            $resolved = parent::resolveRouteBinding($value, $field);

            return $resolved;
        }

        return static::query()
            ->whereKey($value)
            ->orWhere('order_number', $value)
            ->first();
    }
}

<?php

namespace App\Models;

use App\Enums\CheckoutSessionStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\CheckoutSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CheckoutSession extends Model
{
    /** @use HasFactory<CheckoutSessionFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'user_id',
        'cart_id',
        'promotion_id',
        'applied_promotion_code',
        'currency',
        'subtotal',
        'discount_total',
        'discount_breakdown',
        'tax_total',
        'shipping_total',
        'grand_total',
        'shipping_choice',
        'shipping_method',
        'agent_name',
        'agent_contact',
        'cart_fingerprint',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'discount_breakdown' => 'array',
            'tax_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'status' => CheckoutSessionStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    public function isExpired(): bool
    {
        if ($this->status === CheckoutSessionStatus::Expired) {
            return true;
        }

        if ($this->status === CheckoutSessionStatus::Completed) {
            return false;
        }

        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * @param  array{
     *     subtotal: string,
     *     discount_total?: string,
     *     tax_total?: string,
     *     shipping_total?: string,
     *     currency?: string,
     *     discount_breakdown?: array<string, mixed>|null,
     *     promotion_id?: string|null,
     *     applied_promotion_code?: string|null,
     *     grand_total?: string
     * }  $totals
     */
    public function applyTotals(array $totals): void
    {
        $subtotal = $totals['subtotal'];
        $discount = $totals['discount_total'] ?? '0.00';
        $tax = $totals['tax_total'] ?? '0.00';
        $shipping = $totals['shipping_total'] ?? '0.00';

        $grand = $totals['grand_total'] ?? bcsub(
            bcadd(bcadd($subtotal, $shipping, 2), $tax, 2),
            $discount,
            2,
        );

        $payload = [
            'currency' => strtoupper($totals['currency'] ?? $this->currency ?? 'TZS'),
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'tax_total' => $tax,
            'shipping_total' => $shipping,
            'grand_total' => $grand,
        ];

        if (array_key_exists('discount_breakdown', $totals)) {
            $payload['discount_breakdown'] = $totals['discount_breakdown'];
        }
        if (array_key_exists('promotion_id', $totals)) {
            $payload['promotion_id'] = $totals['promotion_id'];
        }
        if (array_key_exists('applied_promotion_code', $totals)) {
            $payload['applied_promotion_code'] = $totals['applied_promotion_code'];
        }

        $this->fill($payload);
    }
}

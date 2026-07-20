<?php

namespace App\Models;

use App\Enums\CouponType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'code',
        'type',
        'value',
        'min_order_amount',
        'max_uses',
        'used_count',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    public function calculateDiscount(string $subtotal): string
    {
        if ($this->min_order_amount !== null && bccomp($subtotal, (string) $this->min_order_amount, 2) < 0) {
            return '0.00';
        }

        if ($this->type === CouponType::Percentage) {
            return bcmul($subtotal, bcdiv((string) $this->value, '100', 4), 2);
        }

        return min((string) $this->value, $subtotal);
    }
}

<?php

namespace App\Models;

use App\Enums\LoyaltyRewardType;
use App\Enums\PromotionDiscountType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyReward extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'code', 'name', 'description', 'reward_type', 'is_active',
        'points_cost', 'discount_type', 'discount_value', 'product_id',
        'usage_limit', 'per_customer_limit', 'redemption_count',
        'channels', 'config', 'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'reward_type' => LoyaltyRewardType::class,
            'discount_type' => PromotionDiscountType::class,
            'is_active' => 'boolean',
            'points_cost' => 'integer',
            'discount_value' => 'decimal:2',
            'usage_limit' => 'integer',
            'per_customer_limit' => 'integer',
            'redemption_count' => 'integer',
            'channels' => 'array',
            'config' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(LoyaltyRedemption::class);
    }

    public function scopeActiveWindow(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }
}

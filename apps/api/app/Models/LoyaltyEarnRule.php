<?php

namespace App\Models;

use App\Enums\LoyaltyEarnRuleType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyEarnRule extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'code', 'name', 'rule_type', 'is_active', 'priority',
        'spend_amount', 'points_awarded', 'product_id', 'category_id',
        'promotion_id', 'bonus_points', 'expiry_months',
        'starts_at', 'ends_at', 'config',
    ];

    protected function casts(): array
    {
        return [
            'rule_type' => LoyaltyEarnRuleType::class,
            'is_active' => 'boolean',
            'priority' => 'integer',
            'spend_amount' => 'decimal:2',
            'points_awarded' => 'integer',
            'bonus_points' => 'integer',
            'expiry_months' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'config' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
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

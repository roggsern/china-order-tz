<?php

namespace App\Models;

use App\Enums\PromotionDiscountType;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'name',
        'code',
        'type',
        'discount_type',
        'value',
        'currency',
        'status',
        'starts_at',
        'ends_at',
        'usage_limit',
        'per_customer_limit',
        'minimum_order_amount',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => PromotionType::class,
            'discount_type' => PromotionDiscountType::class,
            'status' => PromotionStatus::class,
            'value' => 'decimal:2',
            'minimum_order_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'usage_limit' => 'integer',
            'per_customer_limit' => 'integer',
        ];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(PromotionRule::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PromotionUsage::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function scopeActiveWindow(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('status', PromotionStatus::Active)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    public function isWithinDateWindow(?\DateTimeInterface $at = null): bool
    {
        $at = $at ? \Illuminate\Support\Carbon::parse($at) : now();

        if ($this->starts_at !== null && $at->lt($this->starts_at)) {
            return false;
        }
        if ($this->ends_at !== null && $at->gt($this->ends_at)) {
            return false;
        }

        return true;
    }
}

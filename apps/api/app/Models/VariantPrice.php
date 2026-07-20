<?php

namespace App\Models;

use App\Enums\VariantPriceType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\VariantPriceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class VariantPrice extends Model
{
    /** @use HasFactory<VariantPriceFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'variant_prices';

    protected $fillable = [
        'product_variant_id',
        'price_type',
        'currency',
        'amount',
        'compare_at_price',
        'cost_price',
        'minimum_quantity',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'price_type' => VariantPriceType::class,
            'amount' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'minimum_quantity' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function scopeActive(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= now();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $builder) use ($at) {
                $builder->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function (Builder $builder) use ($at) {
                $builder->whereNull('ends_at')->orWhere('ends_at', '>=', $at);
            });
    }

    public function scopeOfType(Builder $query, VariantPriceType|string $type): Builder
    {
        $value = $type instanceof VariantPriceType ? $type->value : $type;

        return $query->where('price_type', $value);
    }

    public function scopeCurrency(Builder $query, string $currency): Builder
    {
        return $query->where('currency', strtoupper($currency));
    }

    public function isCurrentlyActive(?Carbon $at = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $at ??= now();

        if ($this->starts_at !== null && $this->starts_at->gt($at)) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->lt($at)) {
            return false;
        }

        return true;
    }
}

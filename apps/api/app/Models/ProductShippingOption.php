<?php

namespace App\Models;

use App\Enums\ShippingMethod;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\ProductShippingOptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductShippingOption extends Model
{
    /** @use HasFactory<ProductShippingOptionFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'product_id',
        'transport_mode',
        'price',
        'currency',
        'is_available',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'transport_mode' => ShippingMethod::class,
            'price' => 'decimal:2',
            'is_available' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('transport_mode');
    }
}

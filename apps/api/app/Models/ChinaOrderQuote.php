<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChinaOrderQuote extends Model
{
    use HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'china_order_request_id',
        'created_by_admin_id',
        'status',
        'product_cost',
        'sourcing_fee',
        'domestic_shipping',
        'international_shipping',
        'customs_duties',
        'total',
        'currency',
        'valid_until',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'product_cost' => 'decimal:2',
            'sourcing_fee' => 'decimal:2',
            'domestic_shipping' => 'decimal:2',
            'international_shipping' => 'decimal:2',
            'customs_duties' => 'decimal:2',
            'total' => 'decimal:2',
            'valid_until' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ChinaOrderRequest::class, 'china_order_request_id');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChinaOrderQuoteItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ChinaOrderStatusHistory::class);
    }
}

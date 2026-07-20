<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChinaOrderItem extends Model
{
    use HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'china_order_request_id',
        'description',
        'quantity',
        'specs',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ChinaOrderRequest::class, 'china_order_request_id');
    }

    public function sourceLinks(): HasMany
    {
        return $this->hasMany(ChinaOrderSourceLink::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ChinaOrderAttachment::class);
    }

    public function quoteItems(): HasMany
    {
        return $this->hasMany(ChinaOrderQuoteItem::class);
    }
}

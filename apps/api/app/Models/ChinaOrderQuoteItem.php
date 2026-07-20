<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChinaOrderQuoteItem extends Model
{
    use HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'china_order_quote_id',
        'china_order_item_id',
        'description',
        'quantity',
        'unit_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(ChinaOrderQuote::class, 'china_order_quote_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ChinaOrderItem::class, 'china_order_item_id');
    }
}

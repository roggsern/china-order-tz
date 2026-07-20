<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChinaOrderSourceLink extends Model
{
    use HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'china_order_request_id',
        'china_order_item_id',
        'platform',
        'url',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ChinaOrderRequest::class, 'china_order_request_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ChinaOrderItem::class, 'china_order_item_id');
    }
}

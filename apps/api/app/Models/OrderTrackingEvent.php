<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderTrackingEvent extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'order_id',
        'code',
        'status',
        'visibility',
        'source_module',
        'actor_type',
        'actor_id',
        'correlation_key',
        'location',
        'description',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Refund extends Model
{
    use HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'payment_id',
        'order_id',
        'user_id',
        'amount',
        'currency',
        'status',
        'reason',
        'gateway_ref',
        'refunded_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'refunded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

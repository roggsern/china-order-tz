<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'order_id',
        'user_id',
        'method',
        'status',
        'amount',
        'currency',
        'transaction_id',
        'reference',
        'initiated_at',
        'gateway_reference',
        'gateway_session_id',
        'success_indicator',
        'checkout_url',
        'gateway_response',
        'paid_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'initiated_at' => 'datetime',
            'gateway_response' => 'array',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}

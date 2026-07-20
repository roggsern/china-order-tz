<?php

namespace App\Models;

use App\Enums\PaymentProvider;
use App\Enums\PaymentTransactionStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\PaymentTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentTransaction extends Model
{
    /** @use HasFactory<PaymentTransactionFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'order_id',
        'provider',
        'provider_reference',
        'external_transaction_id',
        'merchant_reference',
        'currency',
        'amount',
        'status',
        'request_payload',
        'response_payload',
        'verification_payload',
        'checkout_url',
        'success_indicator',
        'initiated_at',
        'callback_received_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'status' => PaymentTransactionStatus::class,
            'amount' => 'decimal:2',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'verification_payload' => 'array',
            'initiated_at' => 'datetime',
            'callback_received_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

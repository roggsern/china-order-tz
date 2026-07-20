<?php

namespace App\Models;

use App\Enums\RefundTransactionStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\RefundTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundTransaction extends Model
{
    /** @use HasFactory<RefundTransactionFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'return_request_id',
        'order_id',
        'amount',
        'currency',
        'status',
        'method',
        'reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => RefundTransactionStatus::class,
        ];
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

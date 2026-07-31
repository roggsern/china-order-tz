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
        'customer_id',
        'payment_id',
        'amount',
        'currency',
        'status',
        'method',
        'reference',
        'provider_reference',
        'provider_response',
        'notes',
        'reason',
        'created_by_admin_id',
        'approved_by_admin_id',
        'processed_by_admin_id',
        'rejected_by_admin_id',
        'reviewed_at',
        'approved_at',
        'processed_at',
        'completed_at',
        'rejected_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => RefundTransactionStatus::class,
            'provider_response' => 'array',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'processed_at' => 'datetime',
            'completed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'failed_at' => 'datetime',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function approvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }

    public function processedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'processed_by_admin_id');
    }

    public function rejectedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'rejected_by_admin_id');
    }
}

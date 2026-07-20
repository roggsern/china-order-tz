<?php

namespace App\Models;

use App\Enums\PosReturnType;
use App\Enums\ReturnRequestStatus;
use App\Enums\SalesOrigin;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\ReturnRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReturnRequest extends Model
{
    /** @use HasFactory<ReturnRequestFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'return_number',
        'order_id',
        'customer_id',
        'sales_origin',
        'return_type',
        'store_id',
        'pos_session_id',
        'processed_by',
        'return_reason_id',
        'original_receipt_id',
        'refund_method',
        'refund_total',
        'receipt_snapshot',
        'status',
        'reason',
        'description',
        'customer_notes',
        'admin_notes',
        'approved_by',
        'approved_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReturnRequestStatus::class,
            'sales_origin' => SalesOrigin::class,
            'return_type' => PosReturnType::class,
            'refund_total' => 'decimal:2',
            'receipt_snapshot' => 'array',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'pos_session_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'processed_by');
    }

    public function returnReason(): BelongsTo
    {
        return $this->belongsTo(ReturnReason::class, 'return_reason_id');
    }

    public function originalReceipt(): BelongsTo
    {
        return $this->belongsTo(PosReceipt::class, 'original_receipt_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class);
    }

    public function refundTransactions(): HasMany
    {
        return $this->hasMany(RefundTransaction::class);
    }

    public function latestRefund(): HasOne
    {
        return $this->hasOne(RefundTransaction::class)->latestOfMany();
    }
}

<?php

namespace App\Models;

use App\Enums\PosSessionStatus;
use App\Enums\PosSessionVarianceType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSession extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'store_id',
        'terminal_id',
        'admin_id',
        'status',
        'opened_at',
        'closed_at',
        'opening_float',
        'expected_cash',
        'cash_sales',
        'cash_refunds',
        'closing_cash',
        'variance_amount',
        'variance_type',
        'variance_reason',
        'notes',
        'closing_notes',
        'payment_breakdown',
        'transaction_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => PosSessionStatus::class,
            'variance_type' => PosSessionVarianceType::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_float' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'cash_sales' => 'decimal:2',
            'cash_refunds' => 'decimal:2',
            'closing_cash' => 'decimal:2',
            'variance_amount' => 'decimal:2',
            'payment_breakdown' => 'array',
            'transaction_count' => 'integer',
        ];
    }

    public function isOpen(): bool
    {
        return $this->status === PosSessionStatus::Open;
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class, 'terminal_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PosReceipt::class, 'pos_session_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'pos_session_id');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosReceipt extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'order_id',
        'pos_session_id',
        'store_id',
        'receipt_number',
        'issued_at',
        'snapshot',
        'print_count',
        'last_printed_at',
        'last_printed_by',
        'qr_payload',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'qr_payload' => 'array',
            'issued_at' => 'datetime',
            'last_printed_at' => 'datetime',
            'print_count' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'pos_session_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function lastPrintedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'last_printed_by');
    }
}

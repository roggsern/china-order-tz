<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyRedemption extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'loyalty_account_id', 'loyalty_reward_id', 'loyalty_ledger_entry_id',
        'promotion_id', 'promotion_code', 'order_id', 'channel', 'status',
        'points_spent', 'issued_at', 'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'points_spent' => 'integer',
            'issued_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::class, 'loyalty_account_id');
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(LoyaltyReward::class, 'loyalty_reward_id');
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

<?php

namespace App\Models;

use App\Enums\LoyaltyLedgerType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable loyalty ledger. Never update balances without inserting a row.
 */
class LoyaltyLedgerEntry extends Model
{
    use HasUuidPrimaryKey;

    public $timestamps = false;

    protected $fillable = [
        'loyalty_account_id', 'entry_type', 'points', 'balance_after', 'reason',
        'order_id', 'loyalty_earn_rule_id', 'loyalty_reward_id', 'promotion_id',
        'actor_type', 'actor_id', 'expires_at', 'expired_at', 'metadata', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_type' => LoyaltyLedgerType::class,
            'points' => 'integer',
            'balance_after' => 'integer',
            'expires_at' => 'datetime',
            'expired_at' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::class, 'loyalty_account_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(LoyaltyReward::class, 'loyalty_reward_id');
    }
}

<?php

namespace App\Models;

use App\Enums\LoyaltyAccountStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyAccount extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'customer_profile_id', 'loyalty_number', 'loyalty_tier_id', 'status',
        'points_balance', 'lifetime_points', 'lifetime_redeemed',
        'tier_updated_at', 'enrolled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LoyaltyAccountStatus::class,
            'points_balance' => 'integer',
            'lifetime_points' => 'integer',
            'lifetime_redeemed' => 'integer',
            'tier_updated_at' => 'datetime',
            'enrolled_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class, 'customer_profile_id');
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class, 'loyalty_tier_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LoyaltyLedgerEntry::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(LoyaltyRedemption::class);
    }

    public function isActive(): bool
    {
        return $this->status === LoyaltyAccountStatus::Active;
    }
}

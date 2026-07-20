<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyTier extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'code', 'name', 'description', 'sort_order',
        'min_lifetime_points', 'min_lifetime_spend', 'min_orders',
        'earn_multiplier', 'is_active', 'benefits',
    ];

    protected function casts(): array
    {
        return [
            'min_lifetime_points' => 'integer',
            'min_lifetime_spend' => 'decimal:2',
            'min_orders' => 'integer',
            'earn_multiplier' => 'decimal:4',
            'is_active' => 'boolean',
            'benefits' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(LoyaltyAccount::class);
    }
}

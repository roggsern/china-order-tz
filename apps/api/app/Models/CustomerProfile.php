<?php

namespace App\Models;

use App\Enums\CustomerLifecycleStatus;
use App\Enums\CustomerRegistrationSource;
use App\Enums\GrowthStage;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CustomerProfile extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'user_id',
        'customer_code',
        'registration_source',
        'lifecycle_status',
        'blocked_at',
        'blocked_by',
        'block_reason',
        'preferred_language',
        'preferred_currency',
        'date_of_birth',
        'growth_stage',
        'marketing_opt_in',
        'notes_summary',
    ];

    protected function casts(): array
    {
        return [
            'registration_source' => CustomerRegistrationSource::class,
            'lifecycle_status' => CustomerLifecycleStatus::class,
            'growth_stage' => GrowthStage::class,
            'date_of_birth' => 'date',
            'blocked_at' => 'datetime',
            'marketing_opt_in' => 'boolean',
        ];
    }

    public function resolveRouteBinding($value, $field = null): ?static
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        return static::query()
            ->whereKey($value)
            ->orWhere('customer_code', $value)
            ->first();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function blockedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'blocked_by');
    }

    public function metrics(): HasOne
    {
        return $this->hasOne(CustomerMetric::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(CustomerTag::class, 'customer_profile_tag')
            ->withPivot(['assigned_by', 'assigned_at'])
            ->orderBy('customer_tags.name');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class)->latest();
    }

    public function timelineEvents(): HasMany
    {
        return $this->hasMany(CustomerTimelineEvent::class)->latest('occurred_at');
    }

    public function loyaltyAccount(): HasOne
    {
        return $this->hasOne(LoyaltyAccount::class);
    }

    public function scopeForCustomers(Builder $query): Builder
    {
        return $query->whereHas('user.roles', fn (Builder $q) => $q->where('slug', 'customer'));
    }
}

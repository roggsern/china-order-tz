<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrowthSegment extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'code', 'name', 'description', 'rules', 'is_active', 'store_id',
        'created_by', 'last_evaluated_at', 'member_count',
    ];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'is_active' => 'boolean',
            'last_evaluated_at' => 'datetime',
            'member_count' => 'integer',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(CustomerProfile::class, 'growth_segment_members')
            ->withPivot(['matched_at'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(GrowthSegmentMember::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(GrowthCampaign::class);
    }
}

<?php

namespace App\Models;

use App\Enums\GrowthJourneyTrigger;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrowthJourney extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'code', 'name', 'description', 'trigger_type', 'trigger_config',
        'growth_segment_id', 'growth_campaign_id', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'trigger_type' => GrowthJourneyTrigger::class,
            'trigger_config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(GrowthSegment::class, 'growth_segment_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(GrowthCampaign::class, 'growth_campaign_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(GrowthJourneyEnrollment::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthJourneyEnrollment extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'growth_journey_id', 'customer_profile_id', 'status', 'enrolled_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function journey(): BelongsTo
    {
        return $this->belongsTo(GrowthJourney::class, 'growth_journey_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class, 'customer_profile_id');
    }
}

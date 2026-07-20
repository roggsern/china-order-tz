<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthSegmentMember extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'growth_segment_id', 'customer_profile_id', 'matched_at',
    ];

    protected function casts(): array
    {
        return [
            'matched_at' => 'datetime',
        ];
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(GrowthSegment::class, 'growth_segment_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class, 'customer_profile_id');
    }
}

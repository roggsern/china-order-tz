<?php

namespace App\Models;

use App\Enums\CustomerTimelineEventType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerTimelineEvent extends Model
{
    use HasUuidPrimaryKey;

    public $timestamps = false;

    protected $fillable = [
        'customer_profile_id',
        'event_type',
        'subject_type',
        'subject_id',
        'title',
        'description',
        'metadata',
        'occurred_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => CustomerTimelineEventType::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class, 'customer_profile_id');
    }
}

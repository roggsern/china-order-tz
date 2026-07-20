<?php

namespace App\Models;

use App\Enums\GrowthCampaignStatus;
use App\Enums\GrowthCampaignType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrowthCampaign extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'name', 'description', 'campaign_type', 'status', 'growth_segment_id', 'store_id',
        'channel', 'channels', 'message_title', 'message_body',
        'scheduled_at', 'started_at', 'completed_at',
        'promotion_id', 'bonus_points', 'promotion_code',
        'sent_count', 'delivered_count', 'opened_count', 'clicked_count',
        'redeemed_count', 'purchased_count', 'revenue_generated',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'campaign_type' => GrowthCampaignType::class,
            'status' => GrowthCampaignStatus::class,
            'channels' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'bonus_points' => 'integer',
            'sent_count' => 'integer',
            'delivered_count' => 'integer',
            'opened_count' => 'integer',
            'clicked_count' => 'integer',
            'redeemed_count' => 'integer',
            'purchased_count' => 'integer',
            'revenue_generated' => 'decimal:2',
        ];
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(GrowthSegment::class, 'growth_segment_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(GrowthCampaignDelivery::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}

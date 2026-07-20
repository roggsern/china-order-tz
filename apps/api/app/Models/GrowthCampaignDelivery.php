<?php

namespace App\Models;

use App\Enums\GrowthDeliveryStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthCampaignDelivery extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'growth_campaign_id', 'customer_profile_id', 'channel', 'status', 'notification_id',
        'sent_at', 'delivered_at', 'opened_at', 'clicked_at', 'redeemed_at', 'purchased_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => GrowthDeliveryStatus::class,
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'redeemed_at' => 'datetime',
            'purchased_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(GrowthCampaign::class, 'growth_campaign_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class, 'customer_profile_id');
    }
}

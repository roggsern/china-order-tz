<?php

namespace App\Services\Growth;

use App\Enums\GrowthCampaignStatus;
use App\Enums\GrowthStage;
use App\Models\CustomerProfile;
use App\Models\GrowthCampaign;
use App\Models\GrowthCampaignDelivery;
use App\Models\GrowthJourney;
use App\Models\GrowthSegment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Growth Platform facade — dashboard, customer offers, orchestration entrypoints.
 */
class GrowthEngine
{
    public function __construct(
        private readonly SegmentEngine $segments,
        private readonly CampaignEngine $campaigns,
        private readonly JourneyEngine $journeys,
    ) {}

    public function segments(): SegmentEngine
    {
        return $this->segments;
    }

    public function campaigns(): CampaignEngine
    {
        return $this->campaigns;
    }

    public function journeys(): JourneyEngine
    {
        return $this->journeys;
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(?string $storeId = null): array
    {
        $this->segments->refreshGrowthStages();

        $segmentQuery = GrowthSegment::query()->where('is_active', true);
        $campaignQuery = GrowthCampaign::query();
        if ($storeId) {
            $segmentQuery->where(fn ($q) => $q->whereNull('store_id')->orWhere('store_id', $storeId));
            $campaignQuery->where(fn ($q) => $q->whereNull('store_id')->orWhere('store_id', $storeId));
        }

        $stages = CustomerProfile::query()
            ->forCustomers()
            ->selectRaw('growth_stage, COUNT(*) as cnt')
            ->groupBy('growth_stage')
            ->pluck('cnt', 'growth_stage')
            ->all();

        $completed = (clone $campaignQuery)->where('status', GrowthCampaignStatus::Completed)->get();
        $revenue = (float) $completed->sum('revenue_generated');
        $sent = (int) $completed->sum('sent_count');
        $purchased = (int) $completed->sum('purchased_count');

        return [
            'active_segments' => $segmentQuery->count(),
            'total_segment_members' => (int) GrowthSegment::query()->sum('member_count'),
            'campaigns_total' => GrowthCampaign::query()->count(),
            'campaigns_completed' => $completed->count(),
            'campaigns_running' => GrowthCampaign::query()->where('status', GrowthCampaignStatus::Running)->count(),
            'journeys_active' => GrowthJourney::query()->where('is_active', true)->count(),
            'campaign_revenue' => round($revenue, 2),
            'campaign_conversion_rate' => $sent > 0 ? round(($purchased / $sent) * 100, 2) : 0.0,
            'lifecycle_distribution' => [
                'new' => (int) ($stages[GrowthStage::New->value] ?? $stages['new'] ?? 0),
                'active' => (int) ($stages[GrowthStage::Active->value] ?? $stages['active'] ?? 0),
                'vip' => (int) ($stages[GrowthStage::Vip->value] ?? $stages['vip'] ?? 0),
                'inactive' => (int) ($stages[GrowthStage::Inactive->value] ?? $stages['inactive'] ?? 0),
                'winback' => (int) ($stages[GrowthStage::Winback->value] ?? $stages['winback'] ?? 0),
            ],
        ];
    }

    /**
     * Personalized offers for a customer account.
     *
     * @return array{offers: list<array<string, mixed>>, history: list<array<string, mixed>>, growth_stage: string|null}
     */
    public function customerOffers(CustomerProfile $profile): array
    {
        $deliveries = GrowthCampaignDelivery::query()
            ->where('customer_profile_id', $profile->id)
            ->with('campaign:id,name,campaign_type,message_title,message_body,promotion_code,bonus_points,status')
            ->latest()
            ->limit(50)
            ->get();

        $offers = $deliveries
            ->filter(fn ($d) => $d->campaign && in_array($d->status->value ?? (string) $d->status, ['sent', 'delivered', 'opened', 'clicked'], true))
            ->map(fn ($d) => [
                'campaign_id' => $d->growth_campaign_id,
                'name' => $d->campaign?->name,
                'title' => $d->campaign?->message_title,
                'body' => $d->campaign?->message_body,
                'promotion_code' => $d->campaign?->promotion_code,
                'bonus_points' => $d->campaign?->bonus_points,
                'channel' => $d->channel,
                'status' => $d->status instanceof \BackedEnum ? $d->status->value : $d->status,
            ])
            ->values()
            ->all();

        $history = $deliveries->map(fn ($d) => [
            'campaign_id' => $d->growth_campaign_id,
            'name' => $d->campaign?->name,
            'status' => $d->status instanceof \BackedEnum ? $d->status->value : $d->status,
            'channel' => $d->channel,
            'sent_at' => $d->sent_at,
        ])->all();

        return [
            'offers' => $offers,
            'history' => $history,
            'growth_stage' => $profile->growth_stage instanceof \BackedEnum
                ? $profile->growth_stage->value
                : $profile->growth_stage,
            'benefits' => [
                'marketing_opt_in' => (bool) $profile->marketing_opt_in,
                'loyalty_points' => (int) ($profile->loyaltyAccount?->points_balance ?? 0),
                'loyalty_tier' => $profile->loyaltyAccount?->tier?->name,
            ],
        ];
    }

    public function paginateSegments(int $perPage = 20): LengthAwarePaginator
    {
        return GrowthSegment::query()->with('store:id,code,name')->latest()->paginate($perPage);
    }

    public function paginateCampaigns(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $q = GrowthCampaign::query()->with(['segment:id,code,name', 'store:id,code,name'])->latest();
        if (! empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (! empty($filters['store_id'])) {
            $q->where('store_id', $filters['store_id']);
        }

        return $q->paginate($perPage);
    }

    public function paginateJourneys(int $perPage = 20): LengthAwarePaginator
    {
        return GrowthJourney::query()->with(['segment:id,code,name', 'campaign:id,name'])->latest()->paginate($perPage);
    }
}

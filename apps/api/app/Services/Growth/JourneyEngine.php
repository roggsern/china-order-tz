<?php

namespace App\Services\Growth;

use App\Enums\GrowthJourneyTrigger;
use App\Events\Audit\GrowthPlatformAudit;
use App\Models\Admin;
use App\Models\CustomerProfile;
use App\Models\GrowthCampaign;
use App\Models\GrowthJourney;
use App\Models\GrowthJourneyEnrollment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Automated customer journeys — triggers enrollments and optional campaign sends.
 */
class JourneyEngine
{
    public function __construct(
        private readonly CampaignEngine $campaigns,
        private readonly SegmentEngine $segments,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?Admin $admin = null): GrowthJourney
    {
        $trigger = GrowthJourneyTrigger::from((string) $data['trigger_type']);

        $journey = GrowthJourney::query()->create([
            'code' => filled($data['code'] ?? null)
                ? Str::upper(Str::slug((string) $data['code'], '_'))
                : Str::upper(Str::slug($data['name'], '_')),
            'name' => trim((string) $data['name']),
            'description' => $data['description'] ?? null,
            'trigger_type' => $trigger,
            'trigger_config' => $data['trigger_config'] ?? [],
            'growth_segment_id' => $data['growth_segment_id'] ?? null,
            'growth_campaign_id' => $data['growth_campaign_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $admin?->id,
        ]);

        event(GrowthPlatformAudit::journeyCreated($journey, $admin));

        return $journey->fresh(['segment', 'campaign']) ?? $journey;
    }

    public function enroll(GrowthJourney $journey, CustomerProfile $profile, ?Admin $admin = null): GrowthJourneyEnrollment
    {
        if (! $journey->is_active) {
            throw ValidationException::withMessages(['journey' => ['Journey is not active.']]);
        }

        $existing = GrowthJourneyEnrollment::query()
            ->where('growth_journey_id', $journey->id)
            ->where('customer_profile_id', $profile->id)
            ->first();
        if ($existing) {
            return $existing;
        }

        $enrollment = GrowthJourneyEnrollment::query()->create([
            'growth_journey_id' => $journey->id,
            'customer_profile_id' => $profile->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        if ($journey->growth_campaign_id) {
            try {
                $campaign = GrowthCampaign::query()->find($journey->growth_campaign_id);
                if ($campaign && $campaign->growth_segment_id === null) {
                    // one-off: temporarily point to journey segment if set
                    if ($journey->growth_segment_id) {
                        $campaign->forceFill(['growth_segment_id' => $journey->growth_segment_id])->save();
                    }
                }
                // Enrollment tracked; campaign send is intentional via admin or runTriggers.
            } catch (\Throwable $e) {
                Log::warning('growth.journey_campaign_attach_failed', ['message' => $e->getMessage()]);
            }
        }

        return $enrollment;
    }

    /**
     * Evaluate active journeys and enroll matching customers. Optionally send linked campaigns.
     *
     * @return array{enrolled: int, campaigns_sent: int}
     */
    public function runTriggers(?Admin $admin = null, bool $sendCampaigns = false): array
    {
        $enrolled = 0;
        $sent = 0;

        $journeys = GrowthJourney::query()->where('is_active', true)->with(['segment', 'campaign'])->get();
        foreach ($journeys as $journey) {
            $profiles = $this->candidatesFor($journey);
            foreach ($profiles as $profile) {
                $before = GrowthJourneyEnrollment::query()
                    ->where('growth_journey_id', $journey->id)
                    ->where('customer_profile_id', $profile->id)
                    ->exists();
                $this->enroll($journey, $profile, $admin);
                if (! $before) {
                    $enrolled++;
                }
            }

            if ($sendCampaigns && $journey->growth_campaign_id && $journey->campaign) {
                $campaign = $journey->campaign;
                if ($journey->growth_segment_id && $campaign->growth_segment_id === null) {
                    $campaign->forceFill(['growth_segment_id' => $journey->growth_segment_id])->save();
                }
                if ($campaign->growth_segment_id) {
                    $this->campaigns->send($campaign->fresh() ?? $campaign, $admin);
                    $sent++;
                }
            }
        }

        return ['enrolled' => $enrolled, 'campaigns_sent' => $sent];
    }

    /**
     * @return \Illuminate\Support\Collection<int, CustomerProfile>
     */
    private function candidatesFor(GrowthJourney $journey): \Illuminate\Support\Collection
    {
        if ($journey->growth_segment_id && $journey->segment) {
            return $this->segments->evaluate($journey->segment);
        }

        $config = $journey->trigger_config ?? [];

        return match ($journey->trigger_type) {
            GrowthJourneyTrigger::Registration => CustomerProfile::query()
                ->forCustomers()
                ->where('created_at', '>=', now()->subDays((int) ($config['within_days'] ?? 1)))
                ->get(),
            GrowthJourneyTrigger::InactiveDays => CustomerProfile::query()
                ->forCustomers()
                ->whereHas('metrics', function ($q) use ($config) {
                    $days = (int) ($config['days'] ?? 90);
                    $q->where('last_order_at', '<=', now()->subDays($days));
                })
                ->get(),
            GrowthJourneyTrigger::VipThreshold => CustomerProfile::query()
                ->forCustomers()
                ->whereHas('metrics', function ($q) use ($config) {
                    $q->where('total_spend', '>=', (float) ($config['min_spend'] ?? 500000));
                })
                ->get(),
            GrowthJourneyTrigger::Birthday => CustomerProfile::query()
                ->forCustomers()
                ->whereNotNull('date_of_birth')
                ->whereMonth('date_of_birth', now()->month)
                ->whereDay('date_of_birth', now()->day)
                ->get(),
            GrowthJourneyTrigger::Manual => collect(),
        };
    }
}

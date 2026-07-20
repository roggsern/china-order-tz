<?php

namespace App\Services\Growth;

use App\Enums\GrowthCampaignStatus;
use App\Enums\GrowthCampaignType;
use App\Enums\GrowthDeliveryStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationEventType;
use App\Enums\PromotionDiscountType;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Events\Audit\GrowthPlatformAudit;
use App\Models\Admin;
use App\Models\GrowthCampaign;
use App\Models\GrowthCampaignDelivery;
use App\Models\GrowthSegment;
use App\Models\Promotion;
use App\Services\Loyalty\LoyaltyEngine;
use App\Services\Notifications\NotificationPlatform;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Campaign orchestration — sends via NotificationPlatform; discounts via PromotionEngine tables;
 * points via LoyaltyEngine.
 */
class CampaignEngine
{
    public function __construct(
        private readonly SegmentEngine $segments,
        private readonly NotificationPlatform $notifications,
        private readonly LoyaltyEngine $loyalty,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?Admin $admin = null): GrowthCampaign
    {
        $type = GrowthCampaignType::from((string) $data['campaign_type']);
        $channels = $data['channels'] ?? [$data['channel'] ?? 'whatsapp'];
        if (! is_array($channels)) {
            $channels = [$channels];
        }

        $campaign = GrowthCampaign::query()->create([
            'name' => trim((string) $data['name']),
            'description' => $data['description'] ?? null,
            'campaign_type' => $type,
            'status' => GrowthCampaignStatus::tryFrom((string) ($data['status'] ?? 'draft')) ?? GrowthCampaignStatus::Draft,
            'growth_segment_id' => $data['growth_segment_id'] ?? null,
            'store_id' => $data['store_id'] ?? null,
            'channel' => (string) ($data['channel'] ?? ($channels[0] ?? 'whatsapp')),
            'channels' => array_values($channels),
            'message_title' => $data['message_title'] ?? $data['name'],
            'message_body' => (string) $data['message_body'],
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'bonus_points' => $data['bonus_points'] ?? null,
            'created_by' => $admin?->id,
        ]);

        if (! empty($data['create_promotion']) && ! empty($data['promotion'])) {
            $promo = $this->attachPromotion($campaign, $data['promotion'], $admin);
            $campaign->forceFill([
                'promotion_id' => $promo->id,
                'promotion_code' => $promo->code,
            ])->save();
        }

        event(GrowthPlatformAudit::campaignCreated($campaign, $admin));

        return $campaign->fresh(['segment', 'promotion']) ?? $campaign;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(GrowthCampaign $campaign, array $data, ?Admin $admin = null): GrowthCampaign
    {
        if (in_array($campaign->status, [GrowthCampaignStatus::Running, GrowthCampaignStatus::Completed], true)
            && ! empty($data['message_body'])) {
            // allow metadata updates only after send for non-message fields
        }

        foreach ([
            'name', 'description', 'message_title', 'message_body', 'scheduled_at',
            'growth_segment_id', 'store_id', 'channel', 'bonus_points',
        ] as $key) {
            if (array_key_exists($key, $data)) {
                $campaign->{$key} = $data[$key];
            }
        }
        if (array_key_exists('channels', $data) && is_array($data['channels'])) {
            $campaign->channels = $data['channels'];
        }
        if (array_key_exists('campaign_type', $data)) {
            $campaign->campaign_type = GrowthCampaignType::from((string) $data['campaign_type']);
        }
        if (array_key_exists('status', $data)) {
            $campaign->status = GrowthCampaignStatus::from((string) $data['status']);
        }
        $campaign->save();
        event(GrowthPlatformAudit::campaignUpdated($campaign, $admin));

        return $campaign->fresh(['segment', 'promotion']) ?? $campaign;
    }

    public function send(GrowthCampaign $campaign, ?Admin $admin = null): GrowthCampaign
    {
        if ($campaign->status === GrowthCampaignStatus::Cancelled) {
            throw ValidationException::withMessages(['campaign' => ['Cancelled campaigns cannot be sent.']]);
        }
        if ($campaign->growth_segment_id === null) {
            throw ValidationException::withMessages(['growth_segment_id' => ['Campaign requires a target segment.']]);
        }

        /** @var GrowthSegment $segment */
        $segment = GrowthSegment::query()->findOrFail($campaign->growth_segment_id);
        $this->segments->refreshMembers($segment);
        $members = $segment->members()->with('user')->get();

        // Do not wrap notification/loyalty side-effects in a single DB transaction —
        // notification rows and ledger posts must commit independently of delivery bookkeeping.
        $campaign->forceFill([
            'status' => GrowthCampaignStatus::Running,
            'started_at' => $campaign->started_at ?? now(),
        ])->save();

        $channels = $this->resolveChannels($campaign);

        foreach ($members as $profile) {
            if ($profile->user_id === null || ! $profile->marketing_opt_in) {
                continue;
            }
            if (GrowthCampaignDelivery::query()
                ->where('growth_campaign_id', $campaign->id)
                ->where('customer_profile_id', $profile->id)
                ->exists()) {
                continue;
            }

            $channel = $channels[0] ?? NotificationChannel::WhatsApp;
            $delivery = GrowthCampaignDelivery::query()->create([
                'growth_campaign_id' => $campaign->id,
                'customer_profile_id' => $profile->id,
                'channel' => $channel->value,
                'status' => GrowthDeliveryStatus::Queued,
            ]);

            try {
                $notifications = $this->notifications->notifyCustomer(
                    NotificationEventType::GrowthCampaign,
                    $profile->user_id,
                    [
                        'campaign_name' => $campaign->name,
                        'message' => $campaign->message_body,
                        'promotion_code' => $campaign->promotion_code,
                        'bonus_points' => $campaign->bonus_points,
                    ],
                    [$channel],
                    $campaign->message_title ?? $campaign->name,
                );

                $notification = $notifications->first();
                $status = $notification?->status instanceof \BackedEnum
                    ? $notification->status->value
                    : (string) ($notification?->status ?? '');
                $delivered = $notification !== null
                    && $status === NotificationDeliveryStatus::Sent->value;

                if (! $delivered) {
                    $delivery->forceFill([
                        'status' => GrowthDeliveryStatus::Failed,
                        'notification_id' => $notification?->id,
                        'metadata' => ['error' => 'Notification provider did not confirm send.'],
                    ])->save();

                    continue;
                }

                $delivery->forceFill([
                    'status' => GrowthDeliveryStatus::Sent,
                    'notification_id' => $notification->id,
                    'sent_at' => now(),
                    'delivered_at' => now(),
                ])->save();

                if ($campaign->bonus_points && $campaign->bonus_points > 0 && $admin) {
                    try {
                        $account = $this->loyalty->ensureAccount($profile);
                        $this->loyalty->adjustPoints(
                            $account,
                            (int) $campaign->bonus_points,
                            'Growth campaign: '.$campaign->name,
                            $admin,
                        );
                    } catch (\Throwable $loyaltyError) {
                        Log::warning('growth.campaign_loyalty_bonus_failed', [
                            'campaign_id' => $campaign->id,
                            'profile_id' => $profile->id,
                            'message' => $loyaltyError->getMessage(),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('growth.campaign_send_failed', [
                    'campaign_id' => $campaign->id,
                    'profile_id' => $profile->id,
                    'message' => $e->getMessage(),
                ]);
                $delivery->forceFill([
                    'status' => GrowthDeliveryStatus::Failed,
                    'metadata' => ['error' => $e->getMessage()],
                ])->save();
            }
        }

        $campaign->forceFill([
            'sent_count' => (int) $campaign->deliveries()->where('status', '!=', GrowthDeliveryStatus::Failed->value)->count(),
            'delivered_count' => (int) $campaign->deliveries()->whereNotNull('delivered_at')->count(),
            'status' => GrowthCampaignStatus::Completed,
            'completed_at' => now(),
        ])->save();

        event(GrowthPlatformAudit::campaignSent($campaign->fresh() ?? $campaign, $admin));

        return $campaign->fresh(['segment', 'deliveries']) ?? $campaign;
    }

    /**
     * @return array{sent: int, delivered: int, opened: int, clicked: int, redeemed: int, purchased: int, conversion_rate: float, revenue_generated: float}
     */
    public function analytics(GrowthCampaign $campaign): array
    {
        $sent = max(1, (int) $campaign->sent_count);
        $purchased = (int) $campaign->purchased_count;

        return [
            'sent' => (int) $campaign->sent_count,
            'delivered' => (int) $campaign->delivered_count,
            'opened' => (int) $campaign->opened_count,
            'clicked' => (int) $campaign->clicked_count,
            'redeemed' => (int) $campaign->redeemed_count,
            'purchased' => $purchased,
            'conversion_rate' => round(($purchased / $sent) * 100, 2),
            'revenue_generated' => (float) $campaign->revenue_generated,
        ];
    }

    public function trackDelivery(
        GrowthCampaignDelivery $delivery,
        GrowthDeliveryStatus $status,
        ?float $revenue = null,
    ): GrowthCampaignDelivery {
        $updates = ['status' => $status];
        $field = match ($status) {
            GrowthDeliveryStatus::Opened => 'opened_at',
            GrowthDeliveryStatus::Clicked => 'clicked_at',
            GrowthDeliveryStatus::Redeemed => 'redeemed_at',
            GrowthDeliveryStatus::Purchased => 'purchased_at',
            GrowthDeliveryStatus::Delivered => 'delivered_at',
            default => null,
        };
        if ($field) {
            $updates[$field] = now();
        }
        $delivery->forceFill($updates)->save();

        $campaign = $delivery->campaign;
        if ($campaign) {
            $campaign->forceFill([
                'opened_count' => $campaign->deliveries()->whereNotNull('opened_at')->count(),
                'clicked_count' => $campaign->deliveries()->whereNotNull('clicked_at')->count(),
                'redeemed_count' => $campaign->deliveries()->whereNotNull('redeemed_at')->count(),
                'purchased_count' => $campaign->deliveries()->whereNotNull('purchased_at')->count(),
                'revenue_generated' => $revenue !== null
                    ? (float) $campaign->revenue_generated + $revenue
                    : $campaign->revenue_generated,
            ])->save();
        }

        return $delivery->fresh() ?? $delivery;
    }

    /**
     * @param  array<string, mixed>  $promo
     */
    private function attachPromotion(GrowthCampaign $campaign, array $promo, ?Admin $admin): Promotion
    {
        $code = strtoupper((string) ($promo['code'] ?? ('GRW-'.Str::upper(Str::random(8)))));

        return Promotion::query()->create([
            'name' => (string) ($promo['name'] ?? ('Campaign: '.$campaign->name)),
            'code' => $code,
            'type' => PromotionType::Coupon,
            'discount_type' => PromotionDiscountType::from((string) ($promo['discount_type'] ?? 'percentage')),
            'value' => $promo['value'] ?? 10,
            'currency' => 'TZS',
            'status' => PromotionStatus::Active,
            'starts_at' => now(),
            'ends_at' => now()->addDays((int) ($promo['days'] ?? 30)),
            'usage_limit' => $promo['usage_limit'] ?? null,
            'per_customer_limit' => $promo['per_customer_limit'] ?? 1,
            'minimum_order_amount' => $promo['minimum_order_amount'] ?? null,
            'created_by' => $admin?->id,
        ]);
    }

    /**
     * @return list<NotificationChannel>
     */
    private function resolveChannels(GrowthCampaign $campaign): array
    {
        $preferred = $campaign->channels ?? [$campaign->channel];
        $order = ['whatsapp', 'email', 'in_app', 'push', 'sms'];
        $resolved = [];
        foreach ($order as $code) {
            if (in_array($code, $preferred, true)) {
                $channel = NotificationChannel::tryFrom($code);
                if ($channel) {
                    $resolved[] = $channel;
                }
            }
        }
        if ($resolved === []) {
            $resolved[] = NotificationChannel::WhatsApp;
        }

        return $resolved;
    }
}

<?php

namespace Database\Seeders;

use App\Enums\GrowthCampaignType;
use App\Enums\GrowthJourneyTrigger;
use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Models\GrowthCampaign;
use App\Models\GrowthJourney;
use App\Models\GrowthSegment;
use App\Models\NotificationTemplate;
use App\Services\Growth\SegmentEngine;
use Illuminate\Database\Seeder;

/**
 * Sample segments, journeys, and notification template for Growth Platform.
 */
class GrowthSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([NotificationChannel::InApp, NotificationChannel::WhatsApp, NotificationChannel::Email] as $channel) {
            $key = NotificationEventType::GrowthCampaign->defaultTemplateKey($channel);
            NotificationTemplate::query()->updateOrCreate(
                ['key' => $key],
                [
                    'name' => 'Growth Campaign ('.$channel->label().')',
                    'channel' => $channel,
                    'subject' => '{{campaign_name}}',
                    'body' => '{{message}}',
                    'is_active' => true,
                ],
            );
        }

        $segments = [
            [
                'code' => 'VIP_CUSTOMERS',
                'name' => 'VIP Customers',
                'description' => 'High spend or frequent buyers',
                'rules' => [
                    'all' => [
                        ['field' => 'total_spend', 'op' => 'gte', 'value' => 500000],
                    ],
                ],
            ],
            [
                'code' => 'INACTIVE_90',
                'name' => 'Inactive Customers',
                'description' => 'No purchase in 90+ days',
                'rules' => [
                    'all' => [
                        ['field' => 'days_since_last_order', 'op' => 'gte', 'value' => 90],
                        ['field' => 'total_orders', 'op' => 'gte', 'value' => 1],
                    ],
                ],
            ],
            [
                'code' => 'NEW_CUSTOMERS',
                'name' => 'New Customers',
                'description' => 'No completed orders yet',
                'rules' => [
                    'all' => [
                        ['field' => 'total_orders', 'op' => 'eq', 'value' => 0],
                    ],
                ],
            ],
            [
                'code' => 'ONE_TIME_BUYERS',
                'name' => 'One-time Buyers',
                'description' => 'Exactly one order',
                'rules' => [
                    'all' => [
                        ['field' => 'total_orders', 'op' => 'eq', 'value' => 1],
                    ],
                ],
            ],
            [
                'code' => 'FREQUENT_BUYERS',
                'name' => 'Frequent Buyers',
                'description' => 'Five or more orders',
                'rules' => [
                    'all' => [
                        ['field' => 'total_orders', 'op' => 'gte', 'value' => 5],
                    ],
                ],
            ],
        ];

        $engine = app(SegmentEngine::class);

        foreach ($segments as $data) {
            $existing = GrowthSegment::query()->where('code', $data['code'])->first();
            if ($existing) {
                $engine->update($existing, [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'rules' => $data['rules'],
                    'is_active' => true,
                ]);
                continue;
            }
            $engine->create($data);
        }

        $newSegment = GrowthSegment::query()->where('code', 'NEW_CUSTOMERS')->first();
        $inactive = GrowthSegment::query()->where('code', 'INACTIVE_90')->first();
        $vip = GrowthSegment::query()->where('code', 'VIP_CUSTOMERS')->first();

        $welcome = GrowthCampaign::query()->updateOrCreate(
            ['name' => 'Welcome New Customers'],
            [
                'description' => 'Welcome message after registration',
                'campaign_type' => GrowthCampaignType::Announcement,
                'status' => 'draft',
                'growth_segment_id' => $newSegment?->id,
                'channel' => 'whatsapp',
                'channels' => ['whatsapp', 'email', 'in_app'],
                'message_title' => 'Welcome to CHINA ORDER TZ',
                'message_body' => 'Thanks for joining. Explore our catalogue and enjoy member benefits.',
            ],
        );

        $winback = GrowthCampaign::query()->updateOrCreate(
            ['name' => 'Win-back Inactive'],
            [
                'description' => 'Re-engage customers idle 90+ days',
                'campaign_type' => GrowthCampaignType::Winback,
                'status' => 'draft',
                'growth_segment_id' => $inactive?->id,
                'channel' => 'whatsapp',
                'channels' => ['whatsapp', 'in_app'],
                'message_title' => 'We miss you',
                'message_body' => 'Come back for exclusive offers selected for you.',
            ],
        );

        GrowthCampaign::query()->updateOrCreate(
            ['name' => 'VIP Appreciation'],
            [
                'description' => 'Thank high-value customers',
                'campaign_type' => GrowthCampaignType::Vip,
                'status' => 'draft',
                'growth_segment_id' => $vip?->id,
                'channel' => 'whatsapp',
                'channels' => ['whatsapp', 'email', 'in_app'],
                'message_title' => 'VIP Appreciation',
                'message_body' => 'Thank you for being a valued customer. Enjoy bonus rewards.',
                'bonus_points' => 50,
            ],
        );

        GrowthJourney::query()->updateOrCreate(
            ['code' => 'WELCOME_ONBOARDING'],
            [
                'name' => 'New Customer Welcome',
                'description' => 'Registration → welcome message',
                'trigger_type' => GrowthJourneyTrigger::Registration,
                'trigger_config' => ['within_days' => 7],
                'growth_segment_id' => $newSegment?->id,
                'growth_campaign_id' => $welcome->id,
                'is_active' => true,
            ],
        );

        GrowthJourney::query()->updateOrCreate(
            ['code' => 'WINBACK_90'],
            [
                'name' => 'Inactive Win-back',
                'description' => 'No purchase 90 days → win-back',
                'trigger_type' => GrowthJourneyTrigger::InactiveDays,
                'trigger_config' => ['days' => 90],
                'growth_segment_id' => $inactive?->id,
                'growth_campaign_id' => $winback->id,
                'is_active' => true,
            ],
        );

        GrowthJourney::query()->updateOrCreate(
            ['code' => 'VIP_APPRECIATION'],
            [
                'name' => 'VIP Appreciation Journey',
                'description' => 'High spending → VIP appreciation',
                'trigger_type' => GrowthJourneyTrigger::VipThreshold,
                'trigger_config' => ['min_spend' => 500000],
                'growth_segment_id' => $vip?->id,
                'is_active' => true,
            ],
        );

        GrowthJourney::query()->updateOrCreate(
            ['code' => 'BIRTHDAY_REWARD'],
            [
                'name' => 'Birthday Reward',
                'description' => 'Customer birthday → special reward',
                'trigger_type' => GrowthJourneyTrigger::Birthday,
                'trigger_config' => [],
                'is_active' => true,
            ],
        );

        $engine->refreshGrowthStages();
    }
}

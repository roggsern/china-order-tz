<?php

namespace Tests\Feature\Growth;

use App\Enums\ActivityEventType;
use App\Enums\GrowthCampaignType;
use App\Enums\GrowthDeliveryStatus;
use App\Enums\GrowthStage;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationEventType;
use App\Models\Admin;
use App\Models\CustomerMetric;
use App\Models\CustomerProfile;
use App\Models\GrowthCampaignDelivery;
use App\Models\LoyaltyAccount;
use App\Models\Role;
use App\Models\User;
use App\Services\Crm\CustomerProfileService;
use App\Services\Growth\CampaignEngine;
use App\Services\Growth\SegmentEngine;
use App\Services\Stores\StoreAssignmentService;
use App\Services\Stores\StoreService;
use Database\Seeders\LoyaltySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GrowthPlatformTest extends TestCase
{
    use RefreshDatabase;

    private StoreService $stores;

    private StoreAssignmentService $assignments;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(LoyaltySeeder::class);
        $this->stores = app(StoreService::class);
        $this->assignments = app(StoreAssignmentService::class);
    }

    public function test_permission_isolation_for_admin_and_customer_routes(): void
    {
        $this->getJson('/api/v1/growth/offers')->assertUnauthorized();
        $this->getJson('/api/v1/admin/growth/dashboard')->assertUnauthorized();

        $user = $this->customerUser();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/growth/offers')->assertOk()->assertJsonPath('success', true);
        $this->getJson('/api/v1/admin/growth/dashboard')->assertUnauthorized();

        $admin = Admin::factory()->create(['is_active' => true]);
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/growth/dashboard')->assertOk()->assertJsonPath('success', true);
        $this->getJson('/api/v1/growth/offers')->assertUnauthorized();
    }

    public function test_dynamic_segment_rules_match_spend_and_orders(): void
    {
        $vip = $this->profileWithMetrics(['total_spend' => 600000, 'total_orders' => 3]);
        $low = $this->profileWithMetrics(['total_spend' => 1000, 'total_orders' => 1]);
        $new = $this->profileWithMetrics(['total_spend' => 0, 'total_orders' => 0]);

        $admin = Admin::factory()->create(['is_active' => true]);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/admin/growth/segments', [
            'code' => 'HIGH_SPEND',
            'name' => 'High Spend',
            'rules' => [
                'all' => [
                    ['field' => 'total_spend', 'op' => 'gte', 'value' => 500000],
                ],
            ],
        ])->assertCreated();

        $segmentId = $response->json('data.id');
        $this->assertSame(1, (int) $response->json('data.member_count'));
        $this->assertDatabaseHas('growth_segment_members', [
            'growth_segment_id' => $segmentId,
            'customer_profile_id' => $vip->id,
        ]);
        $this->assertDatabaseMissing('growth_segment_members', [
            'growth_segment_id' => $segmentId,
            'customer_profile_id' => $low->id,
        ]);
        $this->assertDatabaseMissing('growth_segment_members', [
            'growth_segment_id' => $segmentId,
            'customer_profile_id' => $new->id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::GrowthSegmentCreated->value,
        ]);
    }

    public function test_lifecycle_stages_are_data_driven(): void
    {
        $new = $this->profileWithMetrics(['total_orders' => 0, 'total_spend' => 0]);
        $active = $this->profileWithMetrics([
            'total_orders' => 2,
            'total_spend' => 20000,
            'last_order_at' => now()->subDays(10),
        ]);
        $vip = $this->profileWithMetrics([
            'total_orders' => 12,
            'total_spend' => 600000,
            'last_order_at' => now()->subDays(5),
        ]);
        $inactive = $this->profileWithMetrics([
            'total_orders' => 2,
            'total_spend' => 5000,
            'last_order_at' => now()->subDays(100),
        ]);

        $engine = app(SegmentEngine::class);
        $engine->refreshGrowthStages();

        $this->assertSame(GrowthStage::New, $new->fresh()->growth_stage);
        $this->assertSame(GrowthStage::Active, $active->fresh()->growth_stage);
        $this->assertSame(GrowthStage::Vip, $vip->fresh()->growth_stage);
        $this->assertSame(GrowthStage::Inactive, $inactive->fresh()->growth_stage);
    }

    public function test_campaign_creation_targeting_notification_promotion_and_loyalty(): void
    {
        $optIn = $this->profileWithMetrics(['total_spend' => 600000, 'total_orders' => 4], marketingOptIn: true);
        $optOut = $this->profileWithMetrics(['total_spend' => 700000, 'total_orders' => 5], marketingOptIn: false);

        $admin = Admin::factory()->create(['is_active' => true]);
        Sanctum::actingAs($admin);

        $segment = $this->postJson('/api/v1/admin/growth/segments', [
            'name' => 'VIP Target',
            'rules' => [
                'all' => [
                    ['field' => 'total_spend', 'op' => 'gte', 'value' => 500000],
                ],
            ],
        ])->assertCreated()->json('data');

        $campaign = $this->postJson('/api/v1/admin/growth/campaigns', [
            'name' => 'VIP Promo',
            'campaign_type' => GrowthCampaignType::Vip->value,
            'growth_segment_id' => $segment['id'],
            'channel' => 'in_app',
            'channels' => ['in_app'],
            'message_title' => 'VIP thank you',
            'message_body' => 'Enjoy your VIP reward.',
            'bonus_points' => 25,
            'create_promotion' => true,
            'promotion' => [
                'code' => 'VIPGROW10',
                'discount_type' => 'percentage',
                'value' => 10,
                'days' => 14,
            ],
        ])->assertCreated()->json('data');

        $this->assertNotEmpty($campaign['promotion_code']);
        $this->assertDatabaseHas('promotions', ['code' => 'VIPGROW10']);
        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::GrowthCampaignCreated->value,
        ]);

        $sent = $this->postJson("/api/v1/admin/growth/campaigns/{$campaign['id']}/send")
            ->assertOk()
            ->json('data');

        $this->assertSame(1, (int) $sent['sent_count']);
        $this->assertDatabaseHas('growth_campaign_deliveries', [
            'growth_campaign_id' => $campaign['id'],
            'customer_profile_id' => $optIn->id,
            'status' => GrowthDeliveryStatus::Sent->value,
        ]);
        $this->assertDatabaseMissing('growth_campaign_deliveries', [
            'growth_campaign_id' => $campaign['id'],
            'customer_profile_id' => $optOut->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'customer_id' => $optIn->user_id,
            'event_type' => NotificationEventType::GrowthCampaign->value,
            'channel' => 'in_app',
            'status' => NotificationDeliveryStatus::Sent->value,
        ]);

        $account = LoyaltyAccount::query()->where('customer_profile_id', $optIn->id)->first();
        $this->assertNotNull($account);
        $this->assertSame(25, (int) $account->points_balance);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::GrowthCampaignSent->value,
        ]);

        $analytics = $this->getJson("/api/v1/admin/growth/campaigns/{$campaign['id']}/analytics")
            ->assertOk()
            ->json('data.analytics');
        $this->assertSame(1, (int) $analytics['sent']);
    }

    public function test_whatsapp_unconfigured_does_not_count_as_sent(): void
    {
        $profile = $this->profileWithMetrics(['total_orders' => 1, 'total_spend' => 1000], marketingOptIn: true);
        $admin = Admin::factory()->create(['is_active' => true]);
        $segment = app(SegmentEngine::class)->create([
            'name' => 'All buyers',
            'rules' => ['all' => [['field' => 'total_orders', 'op' => 'gte', 'value' => 1]]],
        ], $admin);

        $campaign = app(CampaignEngine::class)->create([
            'name' => 'WA Test',
            'campaign_type' => GrowthCampaignType::Announcement->value,
            'growth_segment_id' => $segment->id,
            'channel' => 'whatsapp',
            'channels' => ['whatsapp'],
            'message_body' => 'Hello via WhatsApp',
        ], $admin);

        $result = app(CampaignEngine::class)->send($campaign, $admin);
        $this->assertSame(0, (int) $result->sent_count);
        $this->assertDatabaseHas('growth_campaign_deliveries', [
            'growth_campaign_id' => $campaign->id,
            'customer_profile_id' => $profile->id,
            'status' => GrowthDeliveryStatus::Failed->value,
        ]);
    }

    public function test_journey_creation_and_store_isolation(): void
    {
        $super = Admin::factory()->superAdmin()->create();
        $storeA = $this->stores->create([
            'code' => 'GRW-A',
            'name' => 'Growth Store A',
            'is_active' => true,
        ]);
        $storeB = $this->stores->create([
            'code' => 'GRW-B',
            'name' => 'Growth Store B',
            'is_active' => true,
        ]);

        $cashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
            'is_active' => true,
        ]);
        $this->assignments->assign($cashier, $storeA, $super);

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/admin/growth/segments', [
            'name' => 'Store B only',
            'store_id' => $storeB->id,
            'rules' => ['all' => [['field' => 'total_orders', 'op' => 'gte', 'value' => 0]]],
        ])->assertStatus(422);

        $this->postJson('/api/v1/admin/growth/segments', [
            'name' => 'Store A segment',
            'store_id' => $storeA->id,
            'rules' => ['all' => [['field' => 'total_orders', 'op' => 'gte', 'value' => 0]]],
        ])->assertCreated();

        Sanctum::actingAs($super);
        $this->postJson('/api/v1/admin/growth/journeys', [
            'name' => 'Welcome Journey',
            'trigger_type' => 'registration',
            'trigger_config' => ['within_days' => 3],
        ])->assertCreated();

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::GrowthJourneyCreated->value,
        ]);
    }

    public function test_analytics_growth_section_and_customer_history(): void
    {
        $user = $this->customerUser();
        $profile = app(CustomerProfileService::class)->ensureForUser($user);
        $profile->forceFill(['marketing_opt_in' => true])->save();

        $admin = Admin::factory()->superAdmin()->create(['is_active' => true]);
        $segment = app(SegmentEngine::class)->create([
            'name' => 'Everyone',
            'rules' => ['all' => [['field' => 'not_blocked', 'op' => 'eq', 'value' => true]]],
        ], $admin);
        $campaign = app(CampaignEngine::class)->create([
            'name' => 'Offers',
            'campaign_type' => GrowthCampaignType::Retention->value,
            'growth_segment_id' => $segment->id,
            'channel' => 'in_app',
            'channels' => ['in_app'],
            'message_body' => 'Special for you',
        ], $admin);
        app(CampaignEngine::class)->send($campaign, $admin);

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/analytics/growth')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['summary', 'lifecycle_distribution', 'charts']]);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/growth/offers')
            ->assertOk()
            ->assertJsonPath('success', true);
        $history = $this->getJson('/api/v1/growth/history')->assertOk()->json('data');
        $this->assertNotEmpty($history);
    }

    public function test_campaign_tracking_updates_counts(): void
    {
        $profile = $this->profileWithMetrics(['total_orders' => 1], marketingOptIn: true);
        $admin = Admin::factory()->create(['is_active' => true]);
        $segment = app(SegmentEngine::class)->create([
            'name' => 'Trackers',
            'rules' => ['all' => [['field' => 'total_orders', 'op' => 'gte', 'value' => 1]]],
        ], $admin);
        $campaign = app(CampaignEngine::class)->create([
            'name' => 'Track Me',
            'campaign_type' => GrowthCampaignType::Promotion->value,
            'growth_segment_id' => $segment->id,
            'channel' => 'in_app',
            'channels' => ['in_app'],
            'message_body' => 'Click me',
        ], $admin);
        app(CampaignEngine::class)->send($campaign, $admin);

        $delivery = GrowthCampaignDelivery::query()
            ->where('growth_campaign_id', $campaign->id)
            ->where('customer_profile_id', $profile->id)
            ->firstOrFail();

        app(CampaignEngine::class)->trackDelivery($delivery, GrowthDeliveryStatus::Opened);
        app(CampaignEngine::class)->trackDelivery($delivery->fresh(), GrowthDeliveryStatus::Clicked);
        app(CampaignEngine::class)->trackDelivery($delivery->fresh(), GrowthDeliveryStatus::Purchased, 15000);

        $campaign->refresh();
        $this->assertSame(1, (int) $campaign->opened_count);
        $this->assertSame(1, (int) $campaign->clicked_count);
        $this->assertSame(1, (int) $campaign->purchased_count);
        $this->assertEquals(15000.0, (float) $campaign->revenue_generated);
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function profileWithMetrics(array $metrics, bool $marketingOptIn = false): CustomerProfile
    {
        $user = $this->customerUser();
        $profile = app(CustomerProfileService::class)->ensureForUser($user);
        $profile->forceFill(['marketing_opt_in' => $marketingOptIn])->save();

        CustomerMetric::query()->updateOrCreate(
            ['customer_profile_id' => $profile->id],
            array_merge([
                'total_orders' => 0,
                'completed_orders' => 0,
                'cancelled_orders' => 0,
                'total_spend' => 0,
                'total_refunds' => 0,
                'gross_profit_generated' => 0,
                'average_order_value' => 0,
                'currency' => 'TZS',
                'calculated_at' => now(),
            ], $metrics),
        );

        return $profile->fresh(['metrics']) ?? $profile;
    }

    private function customerUser(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::query()->where('slug', 'customer')->firstOrFail();
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }
}

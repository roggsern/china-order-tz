<?php

namespace Tests\Feature\CMS;

use App\Enums\ActivityEventType;
use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsFeaturedItemType;
use App\Enums\CMS\CmsFeaturedSourceType;
use App\Enums\CMS\CmsHomepageSectionType;
use App\Enums\CMS\CmsStatus;
use App\Enums\CommerceChannelCode;
use App\Models\Admin;
use App\Models\CmsCampaign;
use App\Models\CmsFeaturedContent;
use App\Models\CmsHeroSlide;
use App\Models\CmsHomepageLayout;
use App\Models\CmsHomepageSection;
use App\Models\CommerceChannel;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CmsCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        CommerceChannel::query()->updateOrCreate(
            ['code' => CommerceChannelCode::ChinaImport->value],
            ['name' => 'Order From China', 'is_active' => true],
        );
        CommerceChannel::query()->updateOrCreate(
            ['code' => CommerceChannelCode::TzLocal->value],
            ['name' => 'Buy From TZ', 'is_active' => true],
        );
    }

    public function test_campaign_can_be_created_and_audited(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        $layout = CmsHomepageLayout::factory()
            ->forContext(CmsCommerceContext::ChinaImport)
            ->active()
            ->create();

        $this->postJson('/api/v1/admin/cms/campaigns', [
            'name' => 'Black Friday',
            'slug' => 'black-friday-china',
            'commerce_context' => CmsCommerceContext::ChinaImport->value,
            'priority' => 10,
            'cms_homepage_layout_id' => $layout->id,
            'starts_at' => now()->subHour()->toIso8601String(),
            'ends_at' => now()->addDay()->toIso8601String(),
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'black-friday-china')
            ->assertJsonPath('data.cms_homepage_layout_id', $layout->id);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CmsCampaignCreated->value,
        ]);
    }

    public function test_layout_must_match_campaign_commerce_context(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        $tzLayout = CmsHomepageLayout::factory()
            ->forContext(CmsCommerceContext::TzLocal)
            ->active()
            ->create();

        $this->postJson('/api/v1/admin/cms/campaigns', [
            'name' => 'Mismatch',
            'slug' => 'mismatch',
            'commerce_context' => CmsCommerceContext::ChinaImport->value,
            'cms_homepage_layout_id' => $tzLayout->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['cms_homepage_layout_id']);
    }

    public function test_schedule_rejects_invalid_window(): void
    {
        Sanctum::actingAs($this->cmsAdmin());

        $this->postJson('/api/v1/admin/cms/campaigns', [
            'name' => 'Bad',
            'slug' => 'bad-schedule',
            'commerce_context' => CmsCommerceContext::Global->value,
            'starts_at' => now()->addDay()->toIso8601String(),
            'ends_at' => now()->toIso8601String(),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['ends_at']);
    }

    public function test_only_one_default_campaign_per_context(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        $first = CmsCampaign::factory()->active()->forContext(CmsCommerceContext::Global)->create([
            'is_default' => true,
            'default_slot' => CmsCommerceContext::Global->value,
            'slug' => 'default-a',
        ]);
        $second = CmsCampaign::factory()->active()->forContext(CmsCommerceContext::Global)->create([
            'slug' => 'default-b',
        ]);

        $this->putJson("/api/v1/admin/cms/campaigns/{$second->id}", [
            'is_default' => true,
        ])->assertOk()->assertJsonPath('data.is_default', true);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertNull($first->fresh()->default_slot);
    }

    public function test_default_campaign_cannot_be_archived(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        $campaign = CmsCampaign::factory()->active()->create([
            'is_default' => true,
            'default_slot' => CmsCommerceContext::Global->value,
            'commerce_context' => CmsCommerceContext::Global,
        ]);

        $this->postJson("/api/v1/admin/cms/campaigns/{$campaign->id}/archive")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['campaign']);
    }

    public function test_higher_priority_active_campaign_wins_storefront(): void
    {
        $defaultLayout = CmsHomepageLayout::factory()
            ->defaultFor(CmsCommerceContext::ChinaImport)
            ->create(['slug' => 'default-china-home']);

        $campaignLayout = CmsHomepageLayout::factory()
            ->forContext(CmsCommerceContext::ChinaImport)
            ->active()
            ->create(['slug' => 'bf-china-home']);

        CmsHomepageSection::factory()->create([
            'cms_homepage_layout_id' => $campaignLayout->id,
            'section_type' => CmsHomepageSectionType::FeaturedProducts,
            'is_visible' => true,
        ]);

        CmsCampaign::factory()->active()->scheduled()->forContext(CmsCommerceContext::ChinaImport)->create([
            'slug' => 'low-prio',
            'priority' => 1,
            'cms_homepage_layout_id' => $defaultLayout->id,
        ]);
        $winner = CmsCampaign::factory()->active()->scheduled()->forContext(CmsCommerceContext::ChinaImport)->create([
            'slug' => 'high-prio',
            'name' => 'Black Friday',
            'priority' => 100,
            'cms_homepage_layout_id' => $campaignLayout->id,
        ]);

        $response = $this->getJson(
            '/api/v1/storefront/homepage?commerce_context=CHINA_IMPORT',
        )->assertOk();

        $this->assertSame('bf-china-home', $response->json('data.slug'));
        $this->assertSame($winner->id, $response->json('meta.campaign.id'));
        $this->assertSame('Black Friday', $response->json('meta.campaign.name'));
    }

    public function test_future_and_expired_campaigns_do_not_override_default_layout(): void
    {
        $defaultLayout = CmsHomepageLayout::factory()
            ->defaultFor(CmsCommerceContext::TzLocal)
            ->create(['slug' => 'tz-default']);

        $campaignLayout = CmsHomepageLayout::factory()
            ->forContext(CmsCommerceContext::TzLocal)
            ->active()
            ->create(['slug' => 'tz-campaign']);

        CmsCampaign::factory()->active()->forContext(CmsCommerceContext::TzLocal)->create([
            'slug' => 'future',
            'priority' => 50,
            'cms_homepage_layout_id' => $campaignLayout->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(3),
        ]);
        CmsCampaign::factory()->active()->forContext(CmsCommerceContext::TzLocal)->create([
            'slug' => 'expired',
            'priority' => 50,
            'cms_homepage_layout_id' => $campaignLayout->id,
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->subHour(),
        ]);

        $this->getJson('/api/v1/storefront/homepage?commerce_context=TZ_LOCAL')
            ->assertOk()
            ->assertJsonPath('data.slug', 'tz-default')
            ->assertJsonPath('meta.campaign', null);

        unset($defaultLayout);
    }

    public function test_campaign_hero_and_featured_attach_filters_storefront(): void
    {
        Sanctum::actingAs($this->cmsAdmin());

        $layout = CmsHomepageLayout::factory()
            ->forContext(CmsCommerceContext::ChinaImport)
            ->active()
            ->create(['slug' => 'campaign-layout']);

        $heroSection = CmsHomepageSection::factory()->create([
            'cms_homepage_layout_id' => $layout->id,
            'section_type' => CmsHomepageSectionType::Hero,
            'is_visible' => true,
        ]);
        $featSection = CmsHomepageSection::factory()->create([
            'cms_homepage_layout_id' => $layout->id,
            'section_type' => CmsHomepageSectionType::FeaturedProducts,
            'is_visible' => true,
        ]);

        $keepSlide = CmsHeroSlide::factory()->active()->create([
            'cms_homepage_section_id' => $heroSection->id,
            'headline' => 'Keep',
            'position' => 0,
        ]);
        $dropSlide = CmsHeroSlide::factory()->active()->create([
            'cms_homepage_section_id' => $heroSection->id,
            'headline' => 'Drop',
            'position' => 1,
        ]);

        $product = Product::factory()->chinaImport()->create();
        $keepFeat = CmsFeaturedContent::factory()->active()->create([
            'cms_homepage_section_id' => $featSection->id,
            'title' => 'Keep Feat',
            'source_type' => CmsFeaturedSourceType::Manual,
            'configuration' => [
                'item_type' => CmsFeaturedItemType::Product->value,
                'item_ids' => [$product->id],
            ],
        ]);
        CmsFeaturedContent::factory()->active()->create([
            'cms_homepage_section_id' => $featSection->id,
            'title' => 'Drop Feat',
            'source_type' => CmsFeaturedSourceType::Manual,
            'configuration' => [
                'item_type' => CmsFeaturedItemType::Product->value,
                'item_ids' => [$product->id],
            ],
        ]);

        $campaign = CmsCampaign::factory()->active()->scheduled()->forContext(CmsCommerceContext::ChinaImport)->create([
            'slug' => 'curated',
            'priority' => 20,
            'cms_homepage_layout_id' => $layout->id,
        ]);

        $this->putJson("/api/v1/admin/cms/campaigns/{$campaign->id}/hero-slides", [
            'hero_slide_ids' => [$keepSlide->id],
        ])->assertOk();

        $this->putJson("/api/v1/admin/cms/campaigns/{$campaign->id}/featured-contents", [
            'featured_content_ids' => [$keepFeat->id],
        ])->assertOk();

        $response = $this->getJson('/api/v1/storefront/homepage?commerce_context=CHINA_IMPORT')->assertOk();

        $heroHeadlines = collect($response->json('data.sections'))
            ->firstWhere('section_type', 'HERO')['hero_slides'] ?? [];
        $this->assertCount(1, $heroHeadlines);
        $this->assertSame('Keep', $heroHeadlines[0]['headline']);
        $this->assertSame($keepSlide->id, $heroHeadlines[0]['id']);

        $featTitles = collect($response->json('data.sections'))
            ->firstWhere('section_type', 'FEATURED_PRODUCTS')['featured_contents'] ?? [];
        $this->assertCount(1, $featTitles);
        $this->assertSame('Keep Feat', $featTitles[0]['title']);
        unset($dropSlide);
    }

    public function test_cannot_attach_hero_from_another_layout(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        $layoutA = CmsHomepageLayout::factory()->forContext(CmsCommerceContext::Global)->active()->create();
        $layoutB = CmsHomepageLayout::factory()->forContext(CmsCommerceContext::Global)->active()->create();
        $sectionB = CmsHomepageSection::factory()->create([
            'cms_homepage_layout_id' => $layoutB->id,
            'section_type' => CmsHomepageSectionType::Hero,
        ]);
        $slide = CmsHeroSlide::factory()->create(['cms_homepage_section_id' => $sectionB->id]);
        $campaign = CmsCampaign::factory()->forContext(CmsCommerceContext::Global)->create([
            'cms_homepage_layout_id' => $layoutA->id,
        ]);

        $this->putJson("/api/v1/admin/cms/campaigns/{$campaign->id}/hero-slides", [
            'hero_slide_ids' => [$slide->id],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['hero_slide_ids']);
    }

    public function test_china_campaign_does_not_serve_tz_context(): void
    {
        $tzDefault = CmsHomepageLayout::factory()
            ->defaultFor(CmsCommerceContext::TzLocal)
            ->create(['slug' => 'tz-only-default']);

        $chinaLayout = CmsHomepageLayout::factory()
            ->forContext(CmsCommerceContext::ChinaImport)
            ->active()
            ->create();

        CmsCampaign::factory()->active()->scheduled()->forContext(CmsCommerceContext::ChinaImport)->create([
            'priority' => 99,
            'cms_homepage_layout_id' => $chinaLayout->id,
        ]);

        $this->getJson('/api/v1/storefront/homepage?commerce_context=TZ_LOCAL&allow_global_fallback=0')
            ->assertOk()
            ->assertJsonPath('data.slug', 'tz-only-default')
            ->assertJsonPath('meta.campaign', null);

        unset($tzDefault);
    }

    public function test_activate_priority_and_archive_are_audited(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        $campaign = CmsCampaign::factory()->create(['status' => CmsStatus::Draft, 'is_default' => false]);

        $this->postJson("/api/v1/admin/cms/campaigns/{$campaign->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->patchJson("/api/v1/admin/cms/campaigns/{$campaign->id}/priority", ['priority' => 42])
            ->assertOk()
            ->assertJsonPath('data.priority', 42);

        $this->postJson("/api/v1/admin/cms/campaigns/{$campaign->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CmsCampaignActivated->value,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CmsCampaignPriorityChanged->value,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CmsCampaignArchived->value,
        ]);
    }

    public function test_cashier_cannot_mutate_campaigns(): void
    {
        $cashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
            'is_active' => true,
        ]);
        Sanctum::actingAs($cashier);

        $this->postJson('/api/v1/admin/cms/campaigns', [
            'name' => 'X',
            'slug' => 'x',
            'commerce_context' => 'GLOBAL',
        ])->assertForbidden();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/admin/cms/campaigns', [
            'name' => 'X',
            'slug' => 'x2',
            'commerce_context' => 'GLOBAL',
        ])->assertUnauthorized();
    }

    private function cmsAdmin(): Admin
    {
        return Admin::factory()->superAdmin()->create(['is_active' => true]);
    }
}

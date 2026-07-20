<?php

namespace Tests\Feature\CMS;

use App\Enums\ActivityEventType;
use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsCtaTargetType;
use App\Enums\CMS\CmsHomepageSectionType;
use App\Enums\CMS\CmsStatus;
use App\Enums\CommerceChannelCode;
use App\Models\Admin;
use App\Models\CmsHeroSlide;
use App\Models\CmsHomepageLayout;
use App\Models\CmsHomepageSection;
use App\Models\CommerceChannel;
use App\Models\Media;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Stores\StoreService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CmsHeroExperienceTest extends TestCase
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

    public function test_hero_slide_can_be_created_under_hero_section_with_media(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        [$layout, $section] = $this->heroLayoutAndSection(CmsCommerceContext::ChinaImport);
        $desktop = Media::factory()->image()->create();
        $mobile = Media::factory()->image()->create();

        $response = $this->postJson($this->slidesUrl($layout, $section), [
            'name' => 'Launch Slide',
            'headline' => 'Order from China',
            'desktop_media_id' => $desktop->id,
            'mobile_media_id' => $mobile->id,
            'status' => CmsStatus::Draft->value,
            'position' => 0,
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.headline', 'Order from China')
            ->assertJsonPath('data.desktop_media_id', $desktop->id);

        $this->assertDatabaseHas('cms_hero_slides', [
            'cms_homepage_section_id' => $section->id,
            'name' => 'Launch Slide',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CmsHeroSlideCreated->value,
        ]);
    }

    public function test_hero_slide_cannot_be_created_under_non_hero_section(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        $layout = CmsHomepageLayout::factory()->forContext(CmsCommerceContext::Global)->create();
        $section = CmsHomepageSection::factory()->create([
            'cms_homepage_layout_id' => $layout->id,
            'section_type' => CmsHomepageSectionType::Newsletter,
        ]);

        $this->postJson($this->slidesUrl($layout, $section), [
            'name' => 'Bad',
            'headline' => 'Nope',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['section']);
    }

    public function test_invalid_media_and_video_mime_are_rejected(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        [$layout, $section] = $this->heroLayoutAndSection(CmsCommerceContext::Global);
        $video = Media::factory()->video()->create();

        $this->postJson($this->slidesUrl($layout, $section), [
            'name' => 'Bad media',
            'headline' => 'Headline',
            'desktop_media_id' => '00000000-0000-0000-0000-000000000099',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['desktop_media_id']);

        $this->postJson($this->slidesUrl($layout, $section), [
            'name' => 'Video media',
            'headline' => 'Headline',
            'desktop_media_id' => $video->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['desktop_media_id']);
    }

    public function test_cta_url_rejects_unsafe_schemes(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        [$layout, $section] = $this->heroLayoutAndSection(CmsCommerceContext::Global);

        $this->postJson($this->slidesUrl($layout, $section), [
            'name' => 'Unsafe',
            'headline' => 'Click',
            'primary_cta_label' => 'Go',
            'primary_cta_type' => CmsCtaTargetType::Url->value,
            'primary_cta_value' => 'javascript:alert(1)',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['primary_cta_value']);
    }

    public function test_entity_cta_requires_existing_target(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        [$layout, $section] = $this->heroLayoutAndSection(CmsCommerceContext::ChinaImport);

        $this->postJson($this->slidesUrl($layout, $section), [
            'name' => 'Missing product',
            'headline' => 'Shop',
            'primary_cta_label' => 'Buy',
            'primary_cta_type' => CmsCtaTargetType::Product->value,
            'primary_cta_value' => '00000000-0000-0000-0000-000000000001',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['primary_cta_value']);
    }

    public function test_china_import_hero_cannot_target_tz_store(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        [$layout, $section] = $this->heroLayoutAndSection(CmsCommerceContext::ChinaImport);
        $store = app(StoreService::class)->create([
            'code' => 'TZST',
            'name' => 'TZ Store',
        ], $this->cmsAdmin());

        $this->postJson($this->slidesUrl($layout, $section), [
            'name' => 'Bad store CTA',
            'headline' => 'Shop TZ',
            'primary_cta_label' => 'Visit',
            'primary_cta_type' => CmsCtaTargetType::Store->value,
            'primary_cta_value' => $store->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['primary_cta_value']);
    }

    public function test_tz_local_hero_cannot_target_china_order_form(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        [$layout, $section] = $this->heroLayoutAndSection(CmsCommerceContext::TzLocal);

        $this->postJson($this->slidesUrl($layout, $section), [
            'name' => 'Form CTA',
            'headline' => 'Order',
            'primary_cta_label' => 'Start',
            'primary_cta_type' => CmsCtaTargetType::ChinaOrderForm->value,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['primary_cta_type']);
    }

    public function test_global_hero_rejects_channel_specific_product_cta(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        [$layout, $section] = $this->heroLayoutAndSection(CmsCommerceContext::Global);
        $product = Product::factory()->chinaImport()->create();

        $this->postJson($this->slidesUrl($layout, $section), [
            'name' => 'Global product',
            'headline' => 'Shop',
            'primary_cta_label' => 'Buy',
            'primary_cta_type' => CmsCtaTargetType::Product->value,
            'primary_cta_value' => $product->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['primary_cta_value']);
    }

    public function test_schedule_rejects_ends_at_not_after_starts_at(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        [$layout, $section] = $this->heroLayoutAndSection(CmsCommerceContext::Global);

        $this->postJson($this->slidesUrl($layout, $section), [
            'name' => 'Bad schedule',
            'headline' => 'Soon',
            'starts_at' => now()->addDay()->toIso8601String(),
            'ends_at' => now()->toIso8601String(),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['ends_at']);
    }

    public function test_storefront_eligibility_filters_draft_archived_hidden_future_expired(): void
    {
        $layout = CmsHomepageLayout::factory()
            ->defaultFor(CmsCommerceContext::ChinaImport)
            ->create();
        $section = CmsHomepageSection::factory()->create([
            'cms_homepage_layout_id' => $layout->id,
            'section_type' => CmsHomepageSectionType::Hero,
            'is_visible' => true,
            'position' => 0,
        ]);

        $visible = CmsHeroSlide::factory()->active()->create([
            'cms_homepage_section_id' => $section->id,
            'name' => 'Live',
            'headline' => 'Live Now',
            'position' => 0,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
        ]);
        CmsHeroSlide::factory()->create([
            'cms_homepage_section_id' => $section->id,
            'status' => CmsStatus::Draft,
            'headline' => 'Draft',
            'position' => 1,
        ]);
        CmsHeroSlide::factory()->archived()->create([
            'cms_homepage_section_id' => $section->id,
            'headline' => 'Archived',
            'position' => 2,
        ]);
        CmsHeroSlide::factory()->active()->hidden()->create([
            'cms_homepage_section_id' => $section->id,
            'headline' => 'Hidden',
            'position' => 3,
        ]);
        CmsHeroSlide::factory()->active()->create([
            'cms_homepage_section_id' => $section->id,
            'headline' => 'Future',
            'position' => 4,
            'starts_at' => now()->addDay(),
        ]);
        CmsHeroSlide::factory()->active()->create([
            'cms_homepage_section_id' => $section->id,
            'headline' => 'Expired',
            'position' => 5,
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subMinute(),
        ]);

        $response = $this->getJson(
            '/api/v1/storefront/homepage?commerce_context='.CmsCommerceContext::ChinaImport->value,
        )->assertOk();

        $slides = collect($response->json('data.sections.0.hero_slides'));
        $this->assertCount(1, $slides);
        $this->assertSame('Live Now', $slides[0]['headline']);
        $this->assertSame($visible->id, $slides[0]['id']);
        $this->assertArrayNotHasKey('created_by', $slides[0]);
        $this->assertArrayNotHasKey('status', $slides[0]);
        $this->assertArrayHasKey('primary_cta', $slides[0]);
    }

    public function test_hidden_parent_hero_section_excludes_all_slides(): void
    {
        $layout = CmsHomepageLayout::factory()
            ->defaultFor(CmsCommerceContext::TzLocal)
            ->create();
        $section = CmsHomepageSection::factory()->create([
            'cms_homepage_layout_id' => $layout->id,
            'section_type' => CmsHomepageSectionType::Hero,
            'is_visible' => false,
        ]);
        CmsHeroSlide::factory()->active()->create([
            'cms_homepage_section_id' => $section->id,
            'headline' => 'Should not show',
        ]);

        $response = $this->getJson(
            '/api/v1/storefront/homepage?commerce_context='.CmsCommerceContext::TzLocal->value,
        )->assertOk();

        $this->assertSame([], $response->json('data.sections'));
    }

    public function test_slides_returned_in_position_order_and_reorder_works(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        [$layout, $section] = $this->heroLayoutAndSection(CmsCommerceContext::Global);
        $layout->forceFill([
            'status' => CmsStatus::Active,
            'is_default' => true,
            'default_slot' => CmsCommerceContext::Global->value,
        ])->save();

        $a = CmsHeroSlide::factory()->active()->create([
            'cms_homepage_section_id' => $section->id,
            'headline' => 'A',
            'position' => 0,
        ]);
        $b = CmsHeroSlide::factory()->active()->create([
            'cms_homepage_section_id' => $section->id,
            'headline' => 'B',
            'position' => 1,
        ]);
        $c = CmsHeroSlide::factory()->active()->create([
            'cms_homepage_section_id' => $section->id,
            'headline' => 'C',
            'position' => 2,
        ]);

        $this->putJson($this->slidesUrl($layout, $section).'/reorder', [
            'slide_ids' => [$c->id, $a->id, $b->id],
        ])->assertOk();

        $this->assertSame(0, $c->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
        $this->assertSame(2, $b->fresh()->position);

        $headlines = collect(
            $this->getJson('/api/v1/storefront/homepage?commerce_context=GLOBAL')->json('data.sections.0.hero_slides'),
        )->pluck('headline')->all();
        $this->assertSame(['C', 'A', 'B'], $headlines);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CmsHeroSlidesReordered->value,
        ]);
    }

    public function test_cross_section_and_duplicate_reorder_rejected(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        [$layoutA, $sectionA] = $this->heroLayoutAndSection(CmsCommerceContext::Global, 'layout-a');
        [$layoutB, $sectionB] = $this->heroLayoutAndSection(CmsCommerceContext::Global, 'layout-b');

        $slideA = CmsHeroSlide::factory()->create(['cms_homepage_section_id' => $sectionA->id]);
        $slideB = CmsHeroSlide::factory()->create(['cms_homepage_section_id' => $sectionB->id]);

        $this->putJson($this->slidesUrl($layoutA, $sectionA).'/reorder', [
            'slide_ids' => [$slideA->id, $slideB->id],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['slide_ids']);

        $this->putJson($this->slidesUrl($layoutA, $sectionA).'/reorder', [
            'slide_ids' => [$slideA->id, $slideA->id],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['slide_ids.0']);
    }

    public function test_nested_route_binding_prevents_cross_layout_access(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        [$layoutA, $sectionA] = $this->heroLayoutAndSection(CmsCommerceContext::Global, 'bind-a');
        [$layoutB, $sectionB] = $this->heroLayoutAndSection(CmsCommerceContext::Global, 'bind-b');
        $slide = CmsHeroSlide::factory()->create(['cms_homepage_section_id' => $sectionA->id]);

        $this->getJson($this->slidesUrl($layoutB, $sectionA).'/'.$slide->id)
            ->assertNotFound();

        $this->getJson($this->slidesUrl($layoutA, $sectionB).'/'.$slide->id)
            ->assertNotFound();
    }

    public function test_unauthorized_and_cashier_cannot_mutate_hero_slides(): void
    {
        [$layout, $section] = $this->heroLayoutAndSection(CmsCommerceContext::Global);
        $payload = ['name' => 'X', 'headline' => 'Y'];

        $this->postJson($this->slidesUrl($layout, $section), $payload)->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson($this->slidesUrl($layout, $section), $payload)->assertUnauthorized();

        $cashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
            'is_active' => true,
        ]);
        Sanctum::actingAs($cashier);
        $this->postJson($this->slidesUrl($layout, $section), $payload)->assertForbidden();
    }

    public function test_activate_archive_and_visibility_are_audited(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        [$layout, $section] = $this->heroLayoutAndSection(CmsCommerceContext::Global);
        $slide = CmsHeroSlide::factory()->create([
            'cms_homepage_section_id' => $section->id,
            'status' => CmsStatus::Draft,
        ]);

        $this->postJson($this->slidesUrl($layout, $section).'/'.$slide->id.'/activate')
            ->assertOk()
            ->assertJsonPath('data.status', CmsStatus::Active->value);

        $this->postJson($this->slidesUrl($layout, $section).'/'.$slide->id.'/visibility')
            ->assertOk()
            ->assertJsonPath('data.is_visible', false);

        $this->postJson($this->slidesUrl($layout, $section).'/'.$slide->id.'/archive')
            ->assertOk()
            ->assertJsonPath('data.status', CmsStatus::Archived->value);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CmsHeroSlideActivated->value,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CmsHeroSlideVisibilityChanged->value,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CmsHeroSlideArchived->value,
        ]);
    }

    public function test_china_product_cta_accepted_on_china_layout(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        [$layout, $section] = $this->heroLayoutAndSection(CmsCommerceContext::ChinaImport);
        $product = Product::factory()->chinaImport()->create();

        $this->postJson($this->slidesUrl($layout, $section), [
            'name' => 'Product CTA',
            'headline' => 'Shop product',
            'primary_cta_label' => 'Buy',
            'primary_cta_type' => CmsCtaTargetType::Product->value,
            'primary_cta_value' => $product->id,
            'status' => CmsStatus::Active->value,
        ])->assertCreated()
            ->assertJsonPath('data.primary_cta.type', CmsCtaTargetType::Product->value)
            ->assertJsonPath('data.primary_cta.value', $product->id);
    }

    /**
     * @return array{0: CmsHomepageLayout, 1: CmsHomepageSection}
     */
    private function heroLayoutAndSection(
        CmsCommerceContext $context,
        string $slugSuffix = 'hero',
    ): array {
        $layout = CmsHomepageLayout::factory()->forContext($context)->create([
            'slug' => 'layout-'.$slugSuffix.'-'.uniqid(),
            'status' => CmsStatus::Active,
        ]);
        $section = CmsHomepageSection::factory()->create([
            'cms_homepage_layout_id' => $layout->id,
            'section_type' => CmsHomepageSectionType::Hero,
            'is_visible' => true,
            'position' => 0,
        ]);

        return [$layout, $section];
    }

    private function slidesUrl(CmsHomepageLayout $layout, CmsHomepageSection $section): string
    {
        return "/api/v1/admin/cms/homepage-layouts/{$layout->id}/sections/{$section->id}/hero-slides";
    }

    private function cmsAdmin(): Admin
    {
        return Admin::factory()->superAdmin()->create(['is_active' => true]);
    }
}

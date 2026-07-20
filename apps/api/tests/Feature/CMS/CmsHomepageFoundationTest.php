<?php

namespace Tests\Feature\CMS;

use App\Enums\ActivityEventType;
use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsHomepageSectionType;
use App\Enums\CMS\CmsStatus;
use App\Models\Admin;
use App\Models\CmsHomepageLayout;
use App\Models\CmsHomepageSection;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CmsHomepageFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_homepage_layout_can_be_created(): void
    {
        Sanctum::actingAs($this->cmsAdmin());

        $response = $this->postJson('/api/v1/admin/cms/homepage-layouts', [
            'name' => 'China Home',
            'slug' => 'china-home',
            'commerce_context' => CmsCommerceContext::ChinaImport->value,
            'status' => CmsStatus::Draft->value,
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', 'china-home')
            ->assertJsonPath('data.commerce_context', CmsCommerceContext::ChinaImport->value);

        $this->assertDatabaseHas('cms_homepage_layouts', [
            'slug' => 'china-home',
            'commerce_context' => CmsCommerceContext::ChinaImport->value,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CmsHomepageLayoutCreated->value,
        ]);
    }

    public function test_slug_uniqueness_is_enforced(): void
    {
        Sanctum::actingAs($this->cmsAdmin());

        CmsHomepageLayout::factory()->create(['slug' => 'shared-home']);

        $this->postJson('/api/v1/admin/cms/homepage-layouts', [
            'name' => 'Duplicate',
            'slug' => 'shared-home',
            'commerce_context' => CmsCommerceContext::Global->value,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_layout_can_have_sections_with_configuration_cast(): void
    {
        Sanctum::actingAs($this->cmsAdmin());

        $layout = CmsHomepageLayout::factory()->create([
            'commerce_context' => CmsCommerceContext::TzLocal,
            'created_by' => $this->cmsAdmin()->id,
        ]);

        $response = $this->postJson("/api/v1/admin/cms/homepage-layouts/{$layout->id}/sections", [
            'section_type' => CmsHomepageSectionType::FeaturedProducts->value,
            'title' => 'Featured',
            'position' => 2,
            'configuration' => [
                'limit' => 8,
                'source' => 'catalog',
            ],
        ])->assertCreated();

        $sectionId = $response->json('data.id');
        $section = CmsHomepageSection::query()->findOrFail($sectionId);

        $this->assertIsArray($section->configuration);
        $this->assertSame(8, $section->configuration['limit']);
        $this->assertSame(CmsHomepageSectionType::FeaturedProducts, $section->section_type);
        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CmsHomepageSectionCreated->value,
        ]);
    }

    public function test_only_one_default_layout_per_commerce_context(): void
    {
        Sanctum::actingAs($this->cmsAdmin());

        $first = CmsHomepageLayout::factory()
            ->defaultFor(CmsCommerceContext::ChinaImport)
            ->create(['slug' => 'china-a']);

        $second = CmsHomepageLayout::factory()
            ->forContext(CmsCommerceContext::ChinaImport)
            ->active()
            ->create(['slug' => 'china-b']);

        $this->postJson("/api/v1/admin/cms/homepage-layouts/{$second->id}/default")
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertNull($first->fresh()->default_slot);
        $this->assertTrue($second->fresh()->is_default);
        $this->assertSame(CmsCommerceContext::ChinaImport->value, $second->fresh()->default_slot);

        $this->assertSame(
            1,
            CmsHomepageLayout::query()
                ->where('commerce_context', CmsCommerceContext::ChinaImport->value)
                ->where('is_default', true)
                ->count(),
        );

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CmsHomepageLayoutSetDefault->value,
        ]);
    }

    public function test_default_layout_cannot_be_archived(): void
    {
        Sanctum::actingAs($this->cmsAdmin());

        $layout = CmsHomepageLayout::factory()
            ->defaultFor(CmsCommerceContext::Global)
            ->create();

        $this->postJson("/api/v1/admin/cms/homepage-layouts/{$layout->id}/archive")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['layout']);

        $this->assertSame(CmsStatus::Active, $layout->fresh()->status);
    }

    public function test_sections_can_be_reordered(): void
    {
        Sanctum::actingAs($this->cmsAdmin());

        $layout = CmsHomepageLayout::factory()->create();
        $a = CmsHomepageSection::factory()->create([
            'cms_homepage_layout_id' => $layout->id,
            'position' => 0,
            'section_type' => CmsHomepageSectionType::Hero,
        ]);
        $b = CmsHomepageSection::factory()->create([
            'cms_homepage_layout_id' => $layout->id,
            'position' => 1,
            'section_type' => CmsHomepageSectionType::Newsletter,
        ]);
        $c = CmsHomepageSection::factory()->create([
            'cms_homepage_layout_id' => $layout->id,
            'position' => 2,
            'section_type' => CmsHomepageSectionType::BestSellers,
        ]);

        $this->putJson("/api/v1/admin/cms/homepage-layouts/{$layout->id}/sections/reorder", [
            'section_ids' => [$c->id, $a->id, $b->id],
        ])->assertOk();

        $this->assertSame(0, $c->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
        $this->assertSame(2, $b->fresh()->position);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CmsHomepageSectionsReordered->value,
        ]);
    }

    public function test_sections_from_another_layout_cannot_be_reordered(): void
    {
        Sanctum::actingAs($this->cmsAdmin());

        $layoutA = CmsHomepageLayout::factory()->create(['slug' => 'layout-a']);
        $layoutB = CmsHomepageLayout::factory()->create(['slug' => 'layout-b']);

        $sectionA = CmsHomepageSection::factory()->create(['cms_homepage_layout_id' => $layoutA->id]);
        $sectionB = CmsHomepageSection::factory()->create(['cms_homepage_layout_id' => $layoutB->id]);

        $this->putJson("/api/v1/admin/cms/homepage-layouts/{$layoutA->id}/sections/reorder", [
            'section_ids' => [$sectionA->id, $sectionB->id],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['section_ids']);
    }

    public function test_storefront_excludes_invisible_sections_and_archived_layouts(): void
    {
        $active = CmsHomepageLayout::factory()
            ->defaultFor(CmsCommerceContext::TzLocal)
            ->create(['slug' => 'tz-live']);

        CmsHomepageSection::factory()->create([
            'cms_homepage_layout_id' => $active->id,
            'title' => 'Visible Hero',
            'position' => 0,
            'is_visible' => true,
            'section_type' => CmsHomepageSectionType::Hero,
        ]);
        CmsHomepageSection::factory()->create([
            'cms_homepage_layout_id' => $active->id,
            'title' => 'Hidden Banner',
            'position' => 1,
            'is_visible' => false,
            'section_type' => CmsHomepageSectionType::HomepageBanner,
        ]);

        CmsHomepageLayout::factory()
            ->archived()
            ->forContext(CmsCommerceContext::TzLocal)
            ->create(['slug' => 'tz-old', 'is_default' => false]);

        $response = $this->getJson(
            '/api/v1/storefront/homepage?commerce_context='.CmsCommerceContext::TzLocal->value,
        )->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', 'tz-live');

        $titles = collect($response->json('data.sections'))->pluck('title')->all();
        $this->assertSame(['Visible Hero'], $titles);
    }

    public function test_china_import_retrieval_does_not_return_tz_local_layout(): void
    {
        CmsHomepageLayout::factory()
            ->defaultFor(CmsCommerceContext::TzLocal)
            ->create(['slug' => 'tz-only']);

        CmsHomepageLayout::factory()
            ->defaultFor(CmsCommerceContext::ChinaImport)
            ->create(['slug' => 'china-only']);

        $this->getJson(
            '/api/v1/storefront/homepage?commerce_context='.CmsCommerceContext::ChinaImport->value
            .'&allow_global_fallback=0',
        )->assertOk()
            ->assertJsonPath('data.slug', 'china-only')
            ->assertJsonPath('data.commerce_context', CmsCommerceContext::ChinaImport->value);
    }

    public function test_tz_local_retrieval_does_not_return_china_import_layout(): void
    {
        CmsHomepageLayout::factory()
            ->defaultFor(CmsCommerceContext::ChinaImport)
            ->create(['slug' => 'china-only-2']);

        $this->getJson(
            '/api/v1/storefront/homepage?commerce_context='.CmsCommerceContext::TzLocal->value
            .'&allow_global_fallback=0',
        )->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_global_fallback_is_explicit_and_never_cross_channel(): void
    {
        CmsHomepageLayout::factory()
            ->defaultFor(CmsCommerceContext::Global)
            ->create(['slug' => 'global-home']);

        CmsHomepageLayout::factory()
            ->defaultFor(CmsCommerceContext::ChinaImport)
            ->create(['slug' => 'china-home-fb']);

        $tz = $this->getJson(
            '/api/v1/storefront/homepage?commerce_context='.CmsCommerceContext::TzLocal->value,
        )->assertOk();

        $this->assertSame('global-home', $tz->json('data.slug'));
        $this->assertTrue($tz->json('meta.used_global_fallback'));

        $china = $this->getJson(
            '/api/v1/storefront/homepage?commerce_context='.CmsCommerceContext::ChinaImport->value,
        )->assertOk();

        $this->assertSame('china-home-fb', $china->json('data.slug'));
        $this->assertFalse($china->json('meta.used_global_fallback'));
    }

    public function test_authorization_prevents_unauthorized_cms_modification(): void
    {
        $this->postJson('/api/v1/admin/cms/homepage-layouts', [
            'name' => 'Nope',
            'slug' => 'nope',
            'commerce_context' => CmsCommerceContext::Global->value,
        ])->assertUnauthorized();

        $customer = User::factory()->create();
        Sanctum::actingAs($customer);
        $this->postJson('/api/v1/admin/cms/homepage-layouts', [
            'name' => 'Nope',
            'slug' => 'nope',
            'commerce_context' => CmsCommerceContext::Global->value,
        ])->assertUnauthorized();

        $cashierRole = Role::query()->where('slug', 'store_cashier')->firstOrFail();
        $cashier = Admin::factory()->create([
            'role_id' => $cashierRole->id,
            'is_super_admin' => false,
            'is_active' => true,
        ]);
        Sanctum::actingAs($cashier);

        $this->postJson('/api/v1/admin/cms/homepage-layouts', [
            'name' => 'Cashier Layout',
            'slug' => 'cashier-layout',
            'commerce_context' => CmsCommerceContext::Global->value,
        ])->assertForbidden();
    }

    public function test_section_visibility_toggle_is_audited(): void
    {
        Sanctum::actingAs($this->cmsAdmin());

        $layout = CmsHomepageLayout::factory()->create();
        $section = CmsHomepageSection::factory()->create([
            'cms_homepage_layout_id' => $layout->id,
            'is_visible' => true,
        ]);

        $this->postJson(
            "/api/v1/admin/cms/homepage-layouts/{$layout->id}/sections/{$section->id}/visibility",
        )->assertOk()
            ->assertJsonPath('data.is_visible', false);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CmsHomepageSectionVisibilityChanged->value,
        ]);
    }

    public function test_configuration_rejects_cross_journey_source(): void
    {
        Sanctum::actingAs($this->cmsAdmin());

        $layout = CmsHomepageLayout::factory()
            ->forContext(CmsCommerceContext::ChinaImport)
            ->create();

        $this->postJson("/api/v1/admin/cms/homepage-layouts/{$layout->id}/sections", [
            'section_type' => CmsHomepageSectionType::ShopByStore->value,
            'configuration' => [
                'commerce_context' => CmsCommerceContext::TzLocal->value,
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['configuration.commerce_context']);
    }

    private function cmsAdmin(): Admin
    {
        return Admin::factory()->superAdmin()->create(['is_active' => true]);
    }
}

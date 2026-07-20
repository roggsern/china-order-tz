<?php

namespace Tests\Feature\CMS;

use App\Enums\ActivityEventType;
use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsCtaTargetType;
use App\Enums\CMS\CmsNavigationItemType;
use App\Enums\CMS\CmsNavigationType;
use App\Enums\CMS\CmsNavigationVisibility;
use App\Enums\CMS\CmsStatus;
use App\Enums\CommerceChannelCode;
use App\Models\Admin;
use App\Models\CmsCampaign;
use App\Models\CmsNavigationItem;
use App\Models\CmsNavigationShell;
use App\Models\CommerceChannel;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CmsNavigationShellTest extends TestCase
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

    public function test_navigation_shell_can_be_created_and_audited(): void
    {
        Sanctum::actingAs($this->cmsAdmin());

        $this->postJson('/api/v1/admin/cms/navigation-shells', [
            'name' => 'China Primary',
            'slug' => 'china-primary',
            'commerce_context' => CmsCommerceContext::ChinaImport->value,
            'navigation_type' => CmsNavigationType::Primary->value,
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'china-primary')
            ->assertJsonPath('data.navigation_type', 'PRIMARY');

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CmsNavigationShellCreated->value,
        ]);
    }

    public function test_only_one_default_shell_per_type_and_context(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        $first = CmsNavigationShell::factory()
            ->defaultFor(CmsCommerceContext::ChinaImport, CmsNavigationType::Primary)
            ->create(['slug' => 'default-a']);
        $second = CmsNavigationShell::factory()
            ->forContext(CmsCommerceContext::ChinaImport)
            ->ofType(CmsNavigationType::Primary)
            ->active()
            ->create(['slug' => 'default-b']);

        $this->postJson("/api/v1/admin/cms/navigation-shells/{$second->id}/default")
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertNull($first->fresh()->default_slot);
        $this->assertSame(
            'CHINA_IMPORT:PRIMARY',
            $second->fresh()->default_slot,
        );
    }

    public function test_default_shell_cannot_be_archived(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        $shell = CmsNavigationShell::factory()
            ->defaultFor(CmsCommerceContext::Global, CmsNavigationType::Footer)
            ->create();

        $this->postJson("/api/v1/admin/cms/navigation-shells/{$shell->id}/archive")
            ->assertStatus(422);
    }

    public function test_link_item_validates_cta_targets(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        $shell = CmsNavigationShell::factory()
            ->forContext(CmsCommerceContext::ChinaImport)
            ->ofType(CmsNavigationType::Primary)
            ->active()
            ->create();

        $this->postJson("/api/v1/admin/cms/navigation-shells/{$shell->id}/items", [
            'title' => 'Bad link',
            'item_type' => CmsNavigationItemType::Link->value,
            'target_type' => CmsCtaTargetType::Url->value,
            'target_value' => 'javascript:alert(1)',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['target_value']);

        $this->postJson("/api/v1/admin/cms/navigation-shells/{$shell->id}/items", [
            'title' => 'About',
            'item_type' => CmsNavigationItemType::Link->value,
            'target_type' => CmsCtaTargetType::Url->value,
            'target_value' => 'https://example.com/about',
        ])->assertCreated()
            ->assertJsonPath('data.item_type', 'LINK');
    }

    public function test_journey_and_mega_menu_reject_context_mixing(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        $shell = CmsNavigationShell::factory()
            ->forContext(CmsCommerceContext::ChinaImport)
            ->ofType(CmsNavigationType::Primary)
            ->active()
            ->create();

        $this->postJson("/api/v1/admin/cms/navigation-shells/{$shell->id}/items", [
            'title' => 'TZ journey',
            'item_type' => CmsNavigationItemType::Journey->value,
            'target_value' => CmsCommerceContext::TzLocal->value,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['target_value']);

        $this->postJson("/api/v1/admin/cms/navigation-shells/{$shell->id}/items", [
            'title' => 'China journey',
            'item_type' => CmsNavigationItemType::Journey->value,
            'target_value' => CmsCommerceContext::ChinaImport->value,
        ])->assertCreated();

        $this->postJson("/api/v1/admin/cms/navigation-shells/{$shell->id}/items", [
            'title' => 'China mega',
            'item_type' => CmsNavigationItemType::MegaMenu->value,
            'target_value' => CmsCommerceContext::ChinaImport->value,
        ])->assertCreated();
    }

    public function test_circular_parent_is_rejected(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        $shell = CmsNavigationShell::factory()->active()->create();
        $parent = CmsNavigationItem::factory()->create([
            'navigation_shell_id' => $shell->id,
            'title' => 'Parent',
            'position' => 1,
        ]);
        $child = CmsNavigationItem::factory()->create([
            'navigation_shell_id' => $shell->id,
            'parent_id' => $parent->id,
            'title' => 'Child',
            'position' => 1,
        ]);

        $this->putJson("/api/v1/admin/cms/navigation-shells/{$shell->id}/items/{$parent->id}", [
            'parent_id' => $child->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_reorder_enable_disable_and_delete_are_audited(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        $shell = CmsNavigationShell::factory()->active()->create();
        $a = CmsNavigationItem::factory()->create([
            'navigation_shell_id' => $shell->id,
            'title' => 'A',
            'position' => 0,
        ]);
        $b = CmsNavigationItem::factory()->create([
            'navigation_shell_id' => $shell->id,
            'title' => 'B',
            'position' => 1,
        ]);

        $this->putJson("/api/v1/admin/cms/navigation-shells/{$shell->id}/items/reorder", [
            'items' => [
                ['id' => $b->id, 'position' => 0],
                ['id' => $a->id, 'position' => 1],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CmsNavigationItemsReordered->value,
        ]);

        $this->postJson("/api/v1/admin/cms/navigation-shells/{$shell->id}/items/{$a->id}/disable")
            ->assertOk()
            ->assertJsonPath('data.is_enabled', false);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CmsNavigationItemDisabled->value,
        ]);

        $this->postJson("/api/v1/admin/cms/navigation-shells/{$shell->id}/items/{$a->id}/enable")
            ->assertOk()
            ->assertJsonPath('data.is_enabled', true);

        $this->deleteJson("/api/v1/admin/cms/navigation-shells/{$shell->id}/items/{$b->id}")
            ->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CmsNavigationItemDeleted->value,
        ]);
    }

    public function test_storefront_resolves_default_shell_with_visibility_and_journey(): void
    {
        $shell = CmsNavigationShell::factory()
            ->defaultFor(CmsCommerceContext::ChinaImport, CmsNavigationType::Primary)
            ->create();

        CmsNavigationItem::factory()->journey(CmsCommerceContext::ChinaImport->value)->create([
            'navigation_shell_id' => $shell->id,
            'title' => 'Order from China',
            'position' => 0,
            'visibility' => CmsNavigationVisibility::Public,
        ]);
        CmsNavigationItem::factory()->link()->create([
            'navigation_shell_id' => $shell->id,
            'title' => 'My Orders',
            'position' => 1,
            'visibility' => CmsNavigationVisibility::AuthOnly,
            'target_type' => CmsCtaTargetType::Url,
            'target_value' => 'https://example.com/orders',
        ]);
        CmsNavigationItem::factory()->create([
            'navigation_shell_id' => $shell->id,
            'title' => 'Hidden',
            'position' => 2,
            'is_enabled' => false,
        ]);

        $guest = $this->getJson(
            '/api/v1/storefront/navigation?commerce_context=CHINA_IMPORT&navigation_type=PRIMARY&audience=guest&hydrate_mega_menus=0'
        )->assertOk()
            ->assertJsonPath('data.shell.slug', $shell->slug);

        $guestTitles = collect($guest->json('data.items'))->pluck('title')->all();
        $this->assertContains('Order from China', $guestTitles);
        $this->assertNotContains('My Orders', $guestTitles);
        $this->assertNotContains('Hidden', $guestTitles);
        $this->assertSame('china_storefront_catalog', $guest->json('data.items.0.journey.engine'));

        $auth = $this->getJson(
            '/api/v1/storefront/navigation?commerce_context=CHINA_IMPORT&navigation_type=PRIMARY&audience=authenticated&hydrate_mega_menus=0'
        )->assertOk();
        $authTitles = collect($auth->json('data.items'))->pluck('title')->all();
        $this->assertContains('My Orders', $authTitles);
    }

    public function test_campaign_shell_wins_over_default(): void
    {
        $default = CmsNavigationShell::factory()
            ->defaultFor(CmsCommerceContext::ChinaImport, CmsNavigationType::Primary)
            ->create(['slug' => 'default-primary', 'name' => 'Default Primary']);
        CmsNavigationItem::factory()->create([
            'navigation_shell_id' => $default->id,
            'title' => 'Default Item',
            'position' => 0,
        ]);

        $campaignShell = CmsNavigationShell::factory()
            ->forContext(CmsCommerceContext::ChinaImport)
            ->ofType(CmsNavigationType::Primary)
            ->active()
            ->create(['slug' => 'bf-primary', 'name' => 'BF Primary']);
        CmsNavigationItem::factory()->create([
            'navigation_shell_id' => $campaignShell->id,
            'title' => 'Black Friday Nav',
            'position' => 0,
        ]);

        $campaign = CmsCampaign::factory()
            ->forContext(CmsCommerceContext::ChinaImport)
            ->active()
            ->scheduled()
            ->create(['priority' => 50, 'slug' => 'bf-nav']);
        $campaign->navigationShells()->sync([$campaignShell->id]);

        $this->getJson(
            '/api/v1/storefront/navigation?commerce_context=CHINA_IMPORT&navigation_type=PRIMARY&hydrate_mega_menus=0'
        )->assertOk()
            ->assertJsonPath('data.shell.slug', 'bf-primary')
            ->assertJsonPath('data.campaign.slug', 'bf-nav')
            ->assertJsonPath('data.items.0.title', 'Black Friday Nav');
    }

    public function test_china_campaign_does_not_serve_tz_navigation(): void
    {
        $chinaShell = CmsNavigationShell::factory()
            ->defaultFor(CmsCommerceContext::ChinaImport, CmsNavigationType::Primary)
            ->create();
        CmsNavigationItem::factory()->create([
            'navigation_shell_id' => $chinaShell->id,
            'title' => 'China only',
        ]);

        $this->getJson(
            '/api/v1/storefront/navigation?commerce_context=TZ_LOCAL&navigation_type=PRIMARY&hydrate_mega_menus=0'
        )->assertOk()
            ->assertJsonPath('data.shell', null)
            ->assertJsonPath('data.items', []);
    }

    public function test_mega_menu_hydrates_via_china_engine_reference(): void
    {
        $shell = CmsNavigationShell::factory()
            ->defaultFor(CmsCommerceContext::ChinaImport, CmsNavigationType::Primary)
            ->create();
        CmsNavigationItem::factory()->megaMenu(CmsCommerceContext::ChinaImport->value)->create([
            'navigation_shell_id' => $shell->id,
            'title' => 'China mega',
            'position' => 0,
        ]);

        $this->getJson(
            '/api/v1/storefront/navigation?commerce_context=CHINA_IMPORT&navigation_type=PRIMARY&hydrate_mega_menus=1'
        )->assertOk()
            ->assertJsonPath('data.items.0.mega_menu.engine', 'china_storefront_catalog')
            ->assertJsonPath('data.items.0.mega_menu.journey', 'CHINA_IMPORT')
            ->assertJsonStructure(['data' => ['items' => [['mega_menu' => ['categories']]]]]);
    }

    public function test_publish_shell_is_audited(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        $shell = CmsNavigationShell::factory()->create([
            'status' => CmsStatus::Draft,
        ]);

        $this->postJson("/api/v1/admin/cms/navigation-shells/{$shell->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CmsNavigationShellPublished->value,
        ]);
    }

    public function test_cashier_cannot_mutate_navigation(): void
    {
        $cashierRole = Role::query()->where('slug', 'cashier')->first()
            ?? Role::factory()->create(['slug' => 'cashier', 'name' => 'Cashier']);
        $cashier = Admin::factory()->create([
            'role_id' => $cashierRole->id,
            'is_super_admin' => false,
            'is_active' => true,
        ]);
        Sanctum::actingAs($cashier);

        $this->postJson('/api/v1/admin/cms/navigation-shells', [
            'name' => 'Nope',
            'slug' => 'nope',
            'commerce_context' => 'GLOBAL',
            'navigation_type' => 'PRIMARY',
        ])->assertForbidden();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/admin/cms/navigation-shells', [
            'name' => 'Nope2',
            'slug' => 'nope2',
            'commerce_context' => 'GLOBAL',
            'navigation_type' => 'PRIMARY',
        ])->assertUnauthorized();
    }

    public function test_campaign_rejects_cross_context_navigation_shell(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        $campaign = CmsCampaign::factory()
            ->forContext(CmsCommerceContext::ChinaImport)
            ->active()
            ->create();
        $tzShell = CmsNavigationShell::factory()
            ->forContext(CmsCommerceContext::TzLocal)
            ->ofType(CmsNavigationType::Primary)
            ->active()
            ->create();

        $this->putJson("/api/v1/admin/cms/campaigns/{$campaign->id}/navigation-shells", [
            'navigation_shell_ids' => [$tzShell->id],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['navigation_shell_ids']);
    }

    private function cmsAdmin(): Admin
    {
        return Admin::factory()->superAdmin()->create(['is_active' => true]);
    }
}

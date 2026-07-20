<?php

namespace Tests\Feature\CMS;

use App\Enums\ActivityEventType;
use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsFeaturedDisplayStyle;
use App\Enums\CMS\CmsFeaturedItemType;
use App\Enums\CMS\CmsFeaturedSourceType;
use App\Enums\CMS\CmsHomepageSectionType;
use App\Enums\CMS\CmsStatus;
use App\Enums\CommerceChannelCode;
use App\Models\Admin;
use App\Models\CmsFeaturedContent;
use App\Models\CmsHomepageLayout;
use App\Models\CmsHomepageSection;
use App\Models\CommerceChannel;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Stores\StoreService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CmsFeaturedContentTest extends TestCase
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

    public function test_featured_content_can_be_created_on_featured_products_section(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        [$layout, $section] = $this->featuredSection(CmsCommerceContext::ChinaImport);
        $product = Product::factory()->chinaImport()->create();

        $this->postJson($this->url($layout, $section), [
            'title' => 'Featured picks',
            'source_type' => CmsFeaturedSourceType::Manual->value,
            'display_style' => CmsFeaturedDisplayStyle::Carousel->value,
            'limit' => 4,
            'status' => CmsStatus::Active->value,
            'configuration' => [
                'item_type' => CmsFeaturedItemType::Product->value,
                'item_ids' => [$product->id],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Featured picks')
            ->assertJsonPath('data.source_type', CmsFeaturedSourceType::Manual->value);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CmsFeaturedContentCreated->value,
        ]);
    }

    public function test_hero_section_cannot_own_featured_content(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        $layout = CmsHomepageLayout::factory()->forContext(CmsCommerceContext::ChinaImport)->create();
        $section = CmsHomepageSection::factory()->create([
            'cms_homepage_layout_id' => $layout->id,
            'section_type' => CmsHomepageSectionType::Hero,
        ]);

        $this->postJson($this->url($layout, $section), [
            'title' => 'Nope',
            'source_type' => CmsFeaturedSourceType::NewArrivals->value,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['section']);
    }

    public function test_manual_ids_must_exist_and_match_commerce_context(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        [$layout, $section] = $this->featuredSection(CmsCommerceContext::ChinaImport);
        $tzProduct = Product::factory()->tzLocal()->create();

        $this->postJson($this->url($layout, $section), [
            'title' => 'Bad channel',
            'source_type' => CmsFeaturedSourceType::Manual->value,
            'configuration' => [
                'item_type' => CmsFeaturedItemType::Product->value,
                'item_ids' => [$tzProduct->id],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['configuration.item_ids']);
    }

    public function test_china_layout_cannot_feature_tz_store(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        [$layout, $section] = $this->featuredSection(
            CmsCommerceContext::ChinaImport,
            CmsHomepageSectionType::ShopByStore,
        );
        $store = app(StoreService::class)->create(['code' => 'TZFC', 'name' => 'TZ Featured'], $this->cmsAdmin());

        $this->postJson($this->url($layout, $section), [
            'title' => 'Stores',
            'source_type' => CmsFeaturedSourceType::Manual->value,
            'configuration' => [
                'item_type' => CmsFeaturedItemType::Store->value,
                'item_ids' => [$store->id],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['configuration.item_ids']);
    }

    public function test_global_rejects_ranked_product_sources(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        [$layout, $section] = $this->featuredSection(CmsCommerceContext::Global);

        $this->postJson($this->url($layout, $section), [
            'title' => 'Best',
            'source_type' => CmsFeaturedSourceType::BestSellers->value,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['source_type']);
    }

    public function test_storefront_returns_resolved_items_not_guesswork(): void
    {
        $layout = CmsHomepageLayout::factory()
            ->defaultFor(CmsCommerceContext::ChinaImport)
            ->create();
        $section = CmsHomepageSection::factory()->create([
            'cms_homepage_layout_id' => $layout->id,
            'section_type' => CmsHomepageSectionType::FeaturedProducts,
            'is_visible' => true,
        ]);
        $product = Product::factory()->chinaImport()->create(['name' => 'Resolved Widget']);
        CmsFeaturedContent::factory()->active()->create([
            'cms_homepage_section_id' => $section->id,
            'title' => 'Picks',
            'source_type' => CmsFeaturedSourceType::Manual,
            'configuration' => [
                'item_type' => CmsFeaturedItemType::Product->value,
                'item_ids' => [$product->id],
            ],
            'is_visible' => true,
        ]);
        CmsFeaturedContent::factory()->create([
            'cms_homepage_section_id' => $section->id,
            'status' => CmsStatus::Draft,
            'title' => 'Hidden draft',
            'source_type' => CmsFeaturedSourceType::Manual,
            'configuration' => [
                'item_type' => CmsFeaturedItemType::Product->value,
                'item_ids' => [$product->id],
            ],
        ]);

        $response = $this->getJson(
            '/api/v1/storefront/homepage?commerce_context='.CmsCommerceContext::ChinaImport->value,
        )->assertOk();

        $featured = collect($response->json('data.sections.0.featured_contents'));
        $this->assertCount(1, $featured);
        $this->assertSame('Picks', $featured[0]['title']);
        $this->assertSame(CmsFeaturedSourceType::Manual->value, $featured[0]['source_type']);
        $this->assertArrayHasKey('items', $featured[0]);
        $this->assertSame($product->id, $featured[0]['items'][0]['id']);
        $this->assertSame(CmsFeaturedItemType::Product->value, $featured[0]['items'][0]['item_type']);
        $this->assertSame('Resolved Widget', $featured[0]['items'][0]['data']['name']);
        $this->assertArrayNotHasKey('status', $featured[0]);
        $this->assertArrayNotHasKey('configuration', $featured[0]);
    }

    public function test_new_arrivals_source_resolves_latest_channel_products(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        $layout = CmsHomepageLayout::factory()
            ->defaultFor(CmsCommerceContext::ChinaImport)
            ->create();
        $section = CmsHomepageSection::factory()->create([
            'cms_homepage_layout_id' => $layout->id,
            'section_type' => CmsHomepageSectionType::NewArrivals,
            'is_visible' => true,
        ]);

        $older = Product::factory()->chinaImport()->create(['created_at' => now()->subDays(5)]);
        $newer = Product::factory()->chinaImport()->create(['created_at' => now()->subHour()]);
        Product::factory()->tzLocal()->create(['created_at' => now()]);

        $this->postJson($this->url($layout, $section), [
            'title' => 'New',
            'source_type' => CmsFeaturedSourceType::NewArrivals->value,
            'limit' => 2,
            'status' => CmsStatus::Active->value,
            'configuration' => [],
        ])->assertCreated();

        $items = collect(
            $this->getJson('/api/v1/storefront/homepage?commerce_context=CHINA_IMPORT')
                ->json('data.sections.0.featured_contents.0.items'),
        );

        $this->assertNotEmpty($items);
        $this->assertSame($newer->id, $items[0]['id']);
        $tzIds = Product::query()
            ->whereHas('commerceChannel', fn ($q) => $q->where('code', CommerceChannelCode::TzLocal->value))
            ->pluck('id')
            ->all();
        $this->assertEmpty($items->pluck('id')->intersect($tzIds));
        unset($older);
    }

    public function test_reorder_and_visibility_and_delete_are_audited(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        [$layout, $section] = $this->featuredSection(CmsCommerceContext::ChinaImport);
        $product = Product::factory()->chinaImport()->create();

        $a = CmsFeaturedContent::factory()->create([
            'cms_homepage_section_id' => $section->id,
            'position' => 0,
            'configuration' => ['item_type' => 'PRODUCT', 'item_ids' => [$product->id]],
        ]);
        $b = CmsFeaturedContent::factory()->create([
            'cms_homepage_section_id' => $section->id,
            'position' => 1,
            'configuration' => ['item_type' => 'PRODUCT', 'item_ids' => [$product->id]],
        ]);

        $this->putJson($this->url($layout, $section).'/reorder', [
            'featured_content_ids' => [$b->id, $a->id],
        ])->assertOk();
        $this->assertSame(0, $b->fresh()->position);

        $this->postJson($this->url($layout, $section).'/'.$a->id.'/visibility')
            ->assertOk()
            ->assertJsonPath('data.is_visible', false);

        $this->deleteJson($this->url($layout, $section).'/'.$a->id)->assertOk();
        $this->assertDatabaseMissing('cms_featured_contents', ['id' => $a->id]);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CmsFeaturedContentsReordered->value,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CmsFeaturedContentVisibilityChanged->value,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CmsFeaturedContentDeleted->value,
        ]);
    }

    public function test_cashier_cannot_mutate_featured_content(): void
    {
        [$layout, $section] = $this->featuredSection(CmsCommerceContext::ChinaImport);
        $cashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
            'is_active' => true,
        ]);
        Sanctum::actingAs($cashier);

        $this->postJson($this->url($layout, $section), [
            'title' => 'X',
            'source_type' => CmsFeaturedSourceType::NewArrivals->value,
        ])->assertForbidden();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson($this->url($layout, $section), [
            'title' => 'X',
            'source_type' => CmsFeaturedSourceType::NewArrivals->value,
        ])->assertUnauthorized();
    }

    public function test_nested_binding_rejects_cross_section_access(): void
    {
        Sanctum::actingAs($this->cmsAdmin());
        [$layoutA, $sectionA] = $this->featuredSection(CmsCommerceContext::ChinaImport, slug: 'fa');
        [$layoutB, $sectionB] = $this->featuredSection(CmsCommerceContext::ChinaImport, slug: 'fb');
        $product = Product::factory()->chinaImport()->create();
        $featured = CmsFeaturedContent::factory()->create([
            'cms_homepage_section_id' => $sectionA->id,
            'configuration' => ['item_type' => 'PRODUCT', 'item_ids' => [$product->id]],
        ]);

        $this->getJson($this->url($layoutB, $sectionA).'/'.$featured->id)->assertNotFound();
        $this->getJson($this->url($layoutA, $sectionB).'/'.$featured->id)->assertNotFound();
    }

    /**
     * @return array{0: CmsHomepageLayout, 1: CmsHomepageSection}
     */
    private function featuredSection(
        CmsCommerceContext $context,
        CmsHomepageSectionType $type = CmsHomepageSectionType::FeaturedProducts,
        string $slug = 'feat',
    ): array {
        $layout = CmsHomepageLayout::factory()->forContext($context)->create([
            'slug' => 'layout-'.$slug.'-'.uniqid(),
            'status' => CmsStatus::Active,
        ]);
        $section = CmsHomepageSection::factory()->create([
            'cms_homepage_layout_id' => $layout->id,
            'section_type' => $type,
            'is_visible' => true,
        ]);

        return [$layout, $section];
    }

    private function url(CmsHomepageLayout $layout, CmsHomepageSection $section): string
    {
        return "/api/v1/admin/cms/homepage-layouts/{$layout->id}/sections/{$section->id}/featured-contents";
    }

    private function cmsAdmin(): Admin
    {
        return Admin::factory()->superAdmin()->create(['is_active' => true]);
    }
}

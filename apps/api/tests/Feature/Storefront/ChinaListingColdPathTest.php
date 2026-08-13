<?php

namespace Tests\Feature\Storefront;

use App\Enums\ProductVisibility;
use App\Models\ChinaCommercialStock;
use App\Services\China\Procurement\ChinaCommercialStockService;
use App\Services\Inventory\CatalogStockPresenter;
use Database\Factories\Support\CatalogCartFixture;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChinaListingColdPathTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Cache::flush();
    }

    public function test_commercial_stock_lookup_reuses_eager_loaded_relations(): void
    {
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::chinaPurchasable(28000, 5);
        $product->forceFill([
            'slug' => 'cold-path-eager-stock',
            'visibility' => ProductVisibility::Public,
            'store_id' => null,
        ])->save();

        $product->load(CatalogStockPresenter::catalogListingEagerLoads());
        $variant = $product->variants->firstWhere('id', $variant->id);
        $this->assertNotNull($variant);
        $this->assertTrue($variant->relationLoaded('chinaCommercialStock'));

        DB::flushQueryLog();
        DB::enableQueryLog();

        $row = app(ChinaCommercialStockService::class)->findForProduct($product, $variant);

        $commercialQueries = collect(DB::getQueryLog())
            ->filter(fn (array $q) => str_contains(strtolower($q['query']), 'china_commercial_stocks'))
            ->count();

        $this->assertInstanceOf(ChinaCommercialStock::class, $row);
        $this->assertSame(0, $commercialQueries);
    }

    public function test_china_listing_avoids_commercial_stock_n_plus_one(): void
    {
        ['product' => $productA] = CatalogCartFixture::chinaPurchasable(22000, 4);
        $productA->forceFill([
            'slug' => 'cold-path-list-a',
            'visibility' => ProductVisibility::Public,
            'store_id' => null,
        ])->save();

        ['product' => $productB] = CatalogCartFixture::chinaPurchasable(24000, 3);
        $productB->forceFill([
            'slug' => 'cold-path-list-b',
            'visibility' => ProductVisibility::Public,
            'store_id' => null,
        ])->save();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/v1/storefront/china/products?per_page=12')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 12);

        $commercialLookups = collect(DB::getQueryLog())
            ->filter(function (array $q): bool {
                $sql = strtolower($q['query']);

                return str_contains($sql, 'china_commercial_stocks')
                    && str_contains($sql, 'product_variant_id')
                    && str_contains($sql, 'limit');
            })
            ->count();

        // Pre-fix cold path issued dozens of per-variant LIMIT 1 lookups.
        $this->assertLessThanOrEqual(2, $commercialLookups);
    }

    public function test_china_listing_keeps_pagination_meta_and_slim_variants(): void
    {
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::chinaPurchasable(26000, 6);
        $product->forceFill([
            'slug' => 'cold-path-slim-card',
            'visibility' => ProductVisibility::Public,
            'store_id' => null,
        ])->save();

        $card = $this->getJson('/api/v1/storefront/china/products?per_page=4')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonStructure([
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->json('data.0');

        $this->assertSame('cold-path-slim-card', $card['slug'] ?? null);
        $this->assertSame($variant->id, $card['variants'][0]['id'] ?? null);
        $this->assertArrayNotHasKey('images', $card['variants'][0] ?? []);
        $this->assertArrayNotHasKey('display_attributes', $card['variants'][0] ?? []);
        $this->assertArrayNotHasKey('description', $card);
        $this->assertArrayNotHasKey('videos', $card);
    }
}

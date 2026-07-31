<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Enums\VariantPriceType;
use App\Models\Admin;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCatalogHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_requires_catalog_view_permission(): void
    {
        Sanctum::actingAs(
            Admin::factory()->ordinary()->withPermissions([AdminPermissions::ORDERS_VIEW])->create(),
        );

        $this->getJson('/api/v1/admin/catalog-health')->assertForbidden();
    }

    public function test_guest_cannot_access_catalog_health(): void
    {
        $this->getJson('/api/v1/admin/catalog-health')->assertUnauthorized();
    }

    public function test_response_structure(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $this->getJson('/api/v1/admin/catalog-health')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'health_score',
                        'critical_count',
                        'warning_count',
                    ],
                    'issues' => [
                        'commerce_readiness' => [
                            'active_not_purchasable' => ['severity', 'priority', 'count', 'product_ids'],
                            'missing_valid_price' => ['severity', 'priority', 'count', 'product_ids'],
                            'variants_missing_valid_price' => ['severity', 'priority', 'count', 'variant_ids'],
                        ],
                        'media' => [
                            'active_public_without_images' => ['severity', 'priority', 'count', 'product_ids'],
                            'variants_without_media' => ['severity', 'priority', 'count', 'variant_ids'],
                        ],
                        'inventory' => [
                            'active_missing_inventory_policy' => ['severity', 'priority', 'count', 'product_ids'],
                            'variants_missing_inventory_policy' => ['severity', 'priority', 'count', 'variant_ids'],
                        ],
                        'catalog_quality' => [
                            'variants_without_sku' => ['severity', 'priority', 'count', 'variant_ids'],
                            'variants_without_barcode' => ['severity', 'priority', 'count', 'variant_ids'],
                            'missing_descriptions' => ['severity', 'priority', 'count', 'product_ids'],
                        ],
                    ],
                ],
            ]);
    }

    public function test_detects_active_public_products_without_images(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $withoutImage = $this->makeActivePublicSimpleProduct([
            'slug' => 'health-no-image',
            'name' => 'Health No Image',
        ]);

        $withImage = $this->makeActivePublicSimpleProduct([
            'slug' => 'health-with-image',
            'name' => 'Health With Image',
        ]);
        ProductMedia::factory()->primary()->create([
            'product_id' => $withImage->id,
            'product_variant_id' => null,
            'url' => '/storage/health-primary.jpg',
        ]);

        $response = $this->getJson('/api/v1/admin/catalog-health')->assertOk();

        $issue = $response->json('data.issues.media.active_public_without_images');
        $this->assertSame('critical', $issue['severity']);
        $this->assertSame('P0', $issue['priority']);
        $this->assertGreaterThanOrEqual(1, (int) $issue['count']);
        $this->assertContains($withoutImage->id, $issue['product_ids']);
        $this->assertNotContains($withImage->id, $issue['product_ids']);
    }

    public function test_detects_products_without_valid_prices(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $priced = $this->makeActivePublicSimpleProduct([
            'slug' => 'health-priced',
            'price' => 25000,
        ]);

        $unpriced = $this->makeActivePublicSimpleProduct([
            'slug' => 'health-unpriced',
            'price' => 0,
        ]);

        $response = $this->getJson('/api/v1/admin/catalog-health')->assertOk();

        $issue = $response->json('data.issues.commerce_readiness.missing_valid_price');
        $this->assertSame('critical', $issue['severity']);
        $this->assertContains($unpriced->id, $issue['product_ids']);
        $this->assertNotContains($priced->id, $issue['product_ids']);
    }

    public function test_detects_active_products_failing_purchasability(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $purchasable = $this->makeActivePublicSimpleProduct([
            'slug' => 'health-purchasable',
            'price' => 30000,
        ]);

        $notPurchasable = Product::factory()->create([
            'slug' => 'health-not-purchasable',
            'name' => 'Health Not Purchasable',
            'price' => 0,
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'description' => 'Has description',
            'short_description' => 'Short',
        ]);

        $response = $this->getJson('/api/v1/admin/catalog-health')->assertOk();

        $issue = $response->json('data.issues.commerce_readiness.active_not_purchasable');
        $this->assertSame('critical', $issue['severity']);
        $this->assertContains($notPurchasable->id, $issue['product_ids']);
        $this->assertNotContains($purchasable->id, $issue['product_ids']);
    }

    public function test_detects_active_products_missing_inventory_policy(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $withPolicy = $this->makeActivePublicSimpleProduct([
            'slug' => 'health-with-stock-policy',
            'price' => 20000,
        ]);

        $missingPolicy = Product::factory()->create([
            'slug' => 'health-missing-stock-policy',
            'name' => 'Health Missing Stock Policy',
            'price' => 20000,
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'description' => 'Has description',
            'short_description' => 'Short',
        ]);

        $response = $this->getJson('/api/v1/admin/catalog-health')->assertOk();

        $issue = $response->json('data.issues.inventory.active_missing_inventory_policy');
        $this->assertSame('warning', $issue['severity']);
        $this->assertSame('P1', $issue['priority']);
        $this->assertContains($missingPolicy->id, $issue['product_ids']);
        $this->assertNotContains($withPolicy->id, $issue['product_ids']);
    }

    public function test_detects_variant_media_sku_barcode_and_description_gaps(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->create([
            'slug' => 'health-variant-gaps',
            'name' => 'Health Variant Gaps',
            'price' => 0,
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'description' => null,
            'short_description' => null,
        ]);

        ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'url' => '/storage/product-only.jpg',
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => '',
            'barcode' => null,
            'name' => 'No Media Variant',
            'price' => null,
            'is_active' => true,
            'is_default' => true,
        ]);
        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 15000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 5,
            'reserved' => 0,
            'reorder_level' => 1,
            'safety_stock' => 0,
            'is_active' => true,
        ]);

        $withOwnMedia = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'HEALTH-OWN-MEDIA',
            'barcode' => 'BC-HEALTH-1',
            'name' => 'Own Media Variant',
            'price' => null,
            'is_active' => true,
        ]);
        ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'product_variant_id' => $withOwnMedia->id,
            'url' => '/storage/variant-own.jpg',
        ]);

        $response = $this->getJson('/api/v1/admin/catalog-health')->assertOk();

        $mediaIssue = $response->json('data.issues.media.variants_without_media');
        $this->assertContains($variant->id, $mediaIssue['variant_ids']);
        $this->assertNotContains($withOwnMedia->id, $mediaIssue['variant_ids']);

        $skuIssue = $response->json('data.issues.catalog_quality.variants_without_sku');
        $this->assertContains($variant->id, $skuIssue['variant_ids']);

        $barcodeIssue = $response->json('data.issues.catalog_quality.variants_without_barcode');
        $this->assertContains($variant->id, $barcodeIssue['variant_ids']);
        $this->assertNotContains($withOwnMedia->id, $barcodeIssue['variant_ids']);

        $descriptionIssue = $response->json('data.issues.catalog_quality.missing_descriptions');
        $this->assertContains($product->id, $descriptionIssue['product_ids']);
        $this->assertSame('P2', $descriptionIssue['priority']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeActivePublicSimpleProduct(array $overrides = []): Product
    {
        $product = Product::factory()->create(array_merge([
            'price' => 25000,
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'description' => 'Catalog health description',
            'short_description' => 'Short description',
        ], $overrides));

        Inventory::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => 10,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 2,
            'warehouse_location' => 'MAIN',
        ]);

        return $product->fresh();
    }
}

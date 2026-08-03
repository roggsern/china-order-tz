<?php

namespace Tests\Feature\Pos;

use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Enums\VariantPriceType;
use App\Models\Admin;
use App\Models\CommerceChannel;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\VariantPrice;
use App\Services\Inventory\AdminInventoryApplicationService;
use App\Services\Stores\StoreAssignmentService;
use Database\Seeders\CoreDatabaseSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosCatalogMediaTest extends TestCase
{
    use RefreshDatabase;

    private StoreAssignmentService $assignments;

    private CommerceChannel $tz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(CoreDatabaseSeeder::class);
        $this->assignments = app(StoreAssignmentService::class);
        $this->tz = CommerceChannel::query()->where('code', CommerceChannelCode::TzLocal->value)->firstOrFail();
    }

    public function test_catalog_returns_primary_image_for_simple_product_with_media(): void
    {
        $store = Store::query()->where('slug', 'rovi-beauty')->firstOrFail();
        $cashier = $this->makeCashier($store);

        $product = Product::factory()->create([
            'store_id' => $store->id,
            'commerce_channel_id' => $this->tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
            'price' => 32000,
        ]);

        $media = ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'url' => '/storage/demo-products/pos-simple-wig.jpg',
            'alt_text' => 'POS simple wig',
        ]);

        app(AdminInventoryApplicationService::class)->setSimpleProductStock($product, 12);
        $this->openPosSession($cashier, $store);

        $response = $this->getJson('/api/v1/admin/pos/catalog?q=')
            ->assertOk();

        $row = collect($response->json('data'))
            ->first(fn (array $item) => ($item['product_id'] ?? null) === $product->id);

        $this->assertNotNull($row);
        $this->assertTrue($row['is_simple']);
        $this->assertSame($media->id, $row['primary_image']['id']);
        $this->assertSame('/storage/demo-products/pos-simple-wig.jpg', $row['primary_image']['url']);
        $this->assertSame('POS simple wig', $row['primary_image']['alt_text']);
    }

    public function test_catalog_returns_variant_bound_primary_image(): void
    {
        $store = Store::query()->where('slug', 'rovi-beauty')->firstOrFail();
        $cashier = $this->makeCashier($store);

        $product = Product::factory()->create([
            'store_id' => $store->id,
            'commerce_channel_id' => $this->tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
            'price' => 0,
        ]);

        ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'url' => '/storage/demo-products/product-level.jpg',
            'alt_text' => 'Product level image',
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Large',
            'sku' => 'POS-VAR-L',
            'is_active' => true,
            'price' => 55000,
        ]);

        $variantMedia = ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'url' => '/storage/demo-products/pos-variant-large.jpg',
            'alt_text' => 'Variant large image',
        ]);

        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 55000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        app(AdminInventoryApplicationService::class)->createVariantInventory($variant, ['on_hand' => 8]);

        $this->openPosSession($cashier, $store);

        $response = $this->getJson('/api/v1/admin/pos/catalog?q=POS-VAR-L')
            ->assertOk();

        $row = collect($response->json('data'))
            ->first(fn (array $item) => ($item['product_variant_id'] ?? null) === $variant->id);

        $this->assertNotNull($row);
        $this->assertFalse($row['is_simple']);
        $this->assertSame($variantMedia->id, $row['primary_image']['id']);
        $this->assertSame('/storage/demo-products/pos-variant-large.jpg', $row['primary_image']['url']);
        $this->assertSame('Variant large image', $row['primary_image']['alt_text']);
        $this->assertNotSame('/storage/demo-products/product-level.jpg', $row['primary_image']['url']);
    }

    private function makeCashier(Store $store): Admin
    {
        $super = Admin::factory()->superAdmin()->create();
        $cashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
        ]);
        $this->assignments->assign($cashier, $store, $super);

        return $cashier;
    }

    private function openPosSession(Admin $cashier, Store $store): void
    {
        Sanctum::actingAs($cashier);

        $terminal = PosTerminal::query()->where('store_id', $store->id)->where('is_active', true)->firstOrFail();

        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $store->id,
            'terminal_id' => $terminal->id,
            'opening_float' => 100000,
        ])->assertCreated();
    }
}

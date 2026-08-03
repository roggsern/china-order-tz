<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductLifecycleStatus;
use App\Enums\VariantPriceType;
use App\Models\Admin;
use App\Models\ChinaCommercialStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminVariantBulkActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Sanctum::actingAs(Admin::factory()->create());
    }

    public function test_bulk_set_selling_price_on_china_variants(): void
    {
        [$product, $variants] = $this->chinaVariantProduct(3);

        $response = $this->postJson('/api/v1/admin/products/'.$product->id.'/variants/bulk-action', [
            'action_key' => 'set_selling_price',
            'variant_ids' => $variants->pluck('id')->all(),
            'payload' => ['amount' => 1800000],
        ])->assertOk()
            ->assertJsonPath('data.succeeded', 3)
            ->assertJsonPath('data.failed', 0);

        $this->assertNotEmpty($response->json('data.batch_id'));

        foreach ($variants as $variant) {
            $this->assertDatabaseHas('variant_prices', [
                'product_variant_id' => $variant->id,
                'price_type' => VariantPriceType::Retail->value,
                'amount' => '1800000.00',
            ]);
        }
    }

    public function test_bulk_set_cost_price_on_china_variants(): void
    {
        [$product, $variants] = $this->chinaVariantProduct(3);

        foreach ($variants as $variant) {
            VariantPrice::query()->create([
                'product_variant_id' => $variant->id,
                'price_type' => VariantPriceType::Retail,
                'currency' => 'TZS',
                'amount' => 1500000,
                'minimum_quantity' => 1,
                'is_active' => true,
            ]);
        }

        $this->postJson('/api/v1/admin/products/'.$product->id.'/variants/bulk-action', [
            'action_key' => 'set_cost_price',
            'variant_ids' => $variants->pluck('id')->all(),
            'payload' => ['cost_price' => 1300000],
        ])->assertOk()
            ->assertJsonPath('data.succeeded', 3)
            ->assertJsonPath('data.failed', 0);

        foreach ($variants as $variant) {
            $this->assertDatabaseHas('variant_prices', [
                'product_variant_id' => $variant->id,
                'price_type' => VariantPriceType::Retail->value,
                'cost_price' => '1300000.00',
            ]);
        }
    }

    public function test_bulk_set_commercial_stock_on_china_variants(): void
    {
        [$product, $variants] = $this->chinaVariantProduct(3);

        $this->postJson('/api/v1/admin/products/'.$product->id.'/variants/bulk-action', [
            'action_key' => 'set_commercial_stock',
            'variant_ids' => $variants->pluck('id')->all(),
            'payload' => ['available_quantity' => 25],
        ])->assertOk()
            ->assertJsonPath('data.succeeded', 3)
            ->assertJsonPath('data.failed', 0);

        foreach ($variants as $variant) {
            $this->assertDatabaseHas('china_commercial_stocks', [
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'available_quantity' => 25,
            ]);
        }

        $this->assertSame(3, ChinaCommercialStock::query()->where('product_id', $product->id)->count());
    }

    public function test_bulk_activate_and_deactivate_variants(): void
    {
        [$product, $variants] = $this->chinaVariantProduct(2);
        $variants->each(fn (ProductVariant $variant) => $variant->forceFill(['is_active' => false])->save());

        $this->postJson('/api/v1/admin/products/'.$product->id.'/variants/bulk-action', [
            'action_key' => 'activate',
            'variant_ids' => $variants->pluck('id')->all(),
        ])->assertOk()
            ->assertJsonPath('data.succeeded', 2);

        foreach ($variants as $variant) {
            $this->assertDatabaseHas('product_variants', [
                'id' => $variant->id,
                'is_active' => true,
            ]);
        }

        $this->postJson('/api/v1/admin/products/'.$product->id.'/variants/bulk-action', [
            'action_key' => 'deactivate',
            'variant_ids' => [$variants->first()->id],
        ])->assertOk()
            ->assertJsonPath('data.succeeded', 1);

        $this->assertDatabaseHas('product_variants', [
            'id' => $variants->first()->id,
            'is_active' => false,
        ]);
    }

    public function test_rejects_variants_not_belonging_to_product(): void
    {
        [$product, $variants] = $this->chinaVariantProduct(1);
        $otherProduct = Product::factory()->fromChina()->create([
            'lifecycle_status' => ProductLifecycleStatus::Draft,
        ]);
        $foreignVariant = ProductVariant::factory()->create([
            'product_id' => $otherProduct->id,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/admin/products/'.$product->id.'/variants/bulk-action', [
            'action_key' => 'set_selling_price',
            'variant_ids' => [$foreignVariant->id],
            'payload' => ['amount' => 1000],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['variant_ids.0']);
    }

    public function test_tz_local_commercial_stock_bulk_is_rejected(): void
    {
        $product = Product::factory()->tzLocal()->create([
            'lifecycle_status' => ProductLifecycleStatus::Draft,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 4,
            'reserved' => 0,
            'reorder_level' => 1,
            'safety_stock' => 0,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/admin/products/'.$product->id.'/variants/bulk-action', [
            'action_key' => 'set_commercial_stock',
            'variant_ids' => [$variant->id],
            'payload' => ['available_quantity' => 10],
        ])->assertOk()
            ->assertJsonPath('data.failed', 1)
            ->assertJsonPath('data.results.0.success', false);

        $this->assertSame(0, ChinaCommercialStock::query()->where('product_id', $product->id)->count());
        $this->assertDatabaseHas('variant_inventories', [
            'product_variant_id' => $variant->id,
            'on_hand' => 4,
        ]);
    }

    public function test_tz_local_selling_price_bulk_still_works(): void
    {
        $product = Product::factory()->tzLocal()->create([
            'lifecycle_status' => ProductLifecycleStatus::Draft,
        ]);

        $variants = collect([
            ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]),
            ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]),
        ]);

        $this->postJson('/api/v1/admin/products/'.$product->id.'/variants/bulk-action', [
            'action_key' => 'set_selling_price',
            'variant_ids' => $variants->pluck('id')->all(),
            'payload' => ['amount' => 55000],
        ])->assertOk()
            ->assertJsonPath('data.succeeded', 2);

        foreach ($variants as $variant) {
            $this->assertDatabaseHas('variant_prices', [
                'product_variant_id' => $variant->id,
                'amount' => '55000.00',
            ]);
        }

        $this->assertSame(0, ChinaCommercialStock::query()->where('product_id', $product->id)->count());
    }

    public function test_tz_local_bulk_warehouse_stock_update(): void
    {
        $product = Product::factory()->tzLocal()->create([
            'lifecycle_status' => ProductLifecycleStatus::Draft,
        ]);

        $variants = collect([
            ProductVariant::factory()->create([
                'product_id' => $product->id,
                'sku' => 'TZ-BULK-1',
                'is_active' => true,
            ]),
            ProductVariant::factory()->create([
                'product_id' => $product->id,
                'sku' => 'TZ-BULK-2',
                'is_active' => true,
            ]),
        ]);

        $this->postJson('/api/v1/admin/products/'.$product->id.'/variants/bulk-action', [
            'action_key' => 'set_inventory_stock',
            'variant_ids' => $variants->pluck('id')->all(),
            'payload' => [
                'warehouse_code' => 'MAIN',
                'on_hand' => 18,
                'reserved' => 0,
                'reorder_level' => 2,
                'safety_stock' => 1,
                'is_active' => true,
            ],
        ])->assertOk()
            ->assertJsonPath('data.succeeded', 2)
            ->assertJsonPath('data.failed', 0);

        foreach ($variants as $variant) {
            $this->assertDatabaseHas('variant_inventories', [
                'product_variant_id' => $variant->id,
                'warehouse_code' => 'MAIN',
                'on_hand' => 18,
                'reserved' => 0,
                'reorder_level' => 2,
                'safety_stock' => 1,
                'is_active' => true,
            ]);
        }

        $this->assertSame(0, ChinaCommercialStock::query()->where('product_id', $product->id)->count());
    }

    public function test_china_warehouse_stock_bulk_is_rejected(): void
    {
        [$product, $variants] = $this->chinaVariantProduct(2);

        $this->postJson('/api/v1/admin/products/'.$product->id.'/variants/bulk-action', [
            'action_key' => 'set_inventory_stock',
            'variant_ids' => $variants->pluck('id')->all(),
            'payload' => ['on_hand' => 10],
        ])->assertOk()
            ->assertJsonPath('data.failed', 2)
            ->assertJsonPath('data.results.0.success', false);

        foreach ($variants as $variant) {
            $this->assertDatabaseMissing('variant_inventories', [
                'product_variant_id' => $variant->id,
            ]);
        }
    }

    /**
     * @return array{0: Product, 1: \Illuminate\Support\Collection<int, ProductVariant>}
     */
    private function chinaVariantProduct(int $count): array
    {
        $product = Product::factory()->fromChina()->create([
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'price' => 0,
        ]);

        $variants = collect();
        for ($i = 0; $i < $count; $i++) {
            $variants->push(ProductVariant::factory()->create([
                'product_id' => $product->id,
                'name' => 'Variant '.($i + 1),
                'sku' => 'CN-BULK-'.($i + 1),
                'is_active' => true,
            ]));
        }

        return [$product, $variants];
    }
}

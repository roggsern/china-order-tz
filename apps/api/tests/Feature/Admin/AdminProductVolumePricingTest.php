<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Models\Admin;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\ConfigurationPriceTier;
use App\Models\Department;
use App\Models\Inventory;
use App\Models\Product;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductVolumePricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_multiple_product_level_fixed_unit_tiers(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = $this->simpleSellable(10000);

        $response = $this->syncProductLevelTiers($product, [
            ['min_quantity' => 10, 'tier_type' => 'fixed_unit', 'unit_price' => 8000],
            ['min_quantity' => 50, 'tier_type' => 'fixed_unit', 'unit_price' => 6000],
        ]);

        $tiers = collect($response->json('data'))
            ->sortBy('min_quantity')
            ->values();
        $this->assertSame(10, $tiers[0]['min_quantity']);
        $this->assertSame('8000.00', $tiers[0]['unit_price']);
        $this->assertNull($tiers[0]['configuration_id']);
        $this->assertSame(50, $tiers[1]['min_quantity']);
        $this->assertSame('6000.00', $tiers[1]['unit_price']);

        $this->assertSame(2, ConfigurationPriceTier::query()->where('product_id', $product->id)->count());
        $this->assertDatabaseHas('configuration_price_tiers', [
            'product_id' => $product->id,
            'product_variant_id' => null,
            'min_quantity' => 10,
            'unit_price' => '8000.00',
        ]);
    }

    public function test_canonical_product_put_without_price_tiers_does_not_wipe_existing_tiers(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = $this->editableChinaProduct(['price' => 10000]);
        $this->productTier($product, 10, 8000);
        $this->productTier($product, 50, 6000);

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'name' => 'Renamed volume product',
            'short_description' => 'Still wholesale',
        ])->assertOk();

        $this->assertSame(2, ConfigurationPriceTier::query()->where('product_id', $product->id)->count());
        $this->assertSame('Renamed volume product', $product->fresh()?->name);
    }

    public function test_canonical_product_put_with_price_tiers_saves_product_level_rows(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = $this->editableChinaProduct(['price' => 10000]);

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'price_tiers' => [
                ['min_quantity' => 10, 'tier_type' => 'fixed_unit', 'unit_price' => 8000],
                ['min_quantity' => 50, 'tier_type' => 'fixed_unit', 'unit_price' => 6000],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('configuration_price_tiers', [
            'product_id' => $product->id,
            'product_variant_id' => null,
            'min_quantity' => 10,
            'unit_price' => '8000.00',
        ]);
        $this->assertDatabaseHas('configuration_price_tiers', [
            'product_id' => $product->id,
            'product_variant_id' => null,
            'min_quantity' => 50,
            'unit_price' => '6000.00',
        ]);
    }

    public function test_explicit_empty_price_tiers_clears_product_level_only(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000, 50);
        $this->productTier($product, 10, 8000);
        ConfigurationPriceTier::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'min_quantity' => 5,
            'unit_price' => 7000,
        ]);

        $this->syncProductLevelTiers($product, []);

        $this->assertDatabaseMissing('configuration_price_tiers', [
            'product_id' => $product->id,
            'product_variant_id' => null,
            'min_quantity' => 10,
        ]);
        $this->assertDatabaseHas('configuration_price_tiers', [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'min_quantity' => 5,
            'unit_price' => '7000.00',
        ]);
    }

    public function test_saving_product_level_tiers_does_not_delete_variant_specific_tiers(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000, 50);
        ConfigurationPriceTier::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'min_quantity' => 5,
            'unit_price' => 7000,
        ]);

        $this->syncProductLevelTiers($product, [
            ['min_quantity' => 10, 'tier_type' => 'fixed_unit', 'unit_price' => 8000],
            ['min_quantity' => 50, 'tier_type' => 'fixed_unit', 'unit_price' => 6000],
        ]);

        $this->assertDatabaseHas('configuration_price_tiers', [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'min_quantity' => 5,
            'unit_price' => '7000.00',
        ]);
        $this->assertSame(
            2,
            ConfigurationPriceTier::query()
                ->where('product_id', $product->id)
                ->whereNull('product_variant_id')
                ->count(),
        );
    }

    public function test_configurable_product_accepts_product_level_tiers(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        ['product' => $product] = CatalogCartFixture::purchasable(10000, 50);

        $response = $this->syncProductLevelTiers($product, [
            ['min_quantity' => 10, 'tier_type' => 'fixed_unit', 'unit_price' => 8000],
        ]);

        $this->assertNull($response->json('data.0.configuration_id'));
        $this->assertSame(10, $response->json('data.0.min_quantity'));
        $this->assertNotNull($product->fresh()?->variants()->first());
    }

    public function test_updating_volume_tiers_does_not_change_purchase_quantity_rules(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = $this->simpleSellable(10000);
        $product->forceFill([
            'minimum_order_quantity' => 6,
            'order_increment' => 3,
        ])->save();

        $this->syncProductLevelTiers($product, [
            ['min_quantity' => 10, 'tier_type' => 'fixed_unit', 'unit_price' => 8000],
        ]);

        $fresh = $product->fresh();
        $this->assertSame(6, $fresh?->minimum_order_quantity);
        $this->assertSame(3, $fresh?->order_increment);
    }

    public function test_saved_tiers_keep_threshold_unit_prices_and_in_between_quantities_legal(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = $this->simpleSellable(10000);

        $this->syncProductLevelTiers($product, [
            ['min_quantity' => 10, 'tier_type' => 'fixed_unit', 'unit_price' => 8000],
            ['min_quantity' => 50, 'tier_type' => 'fixed_unit', 'unit_price' => 6000],
        ]);

        $slug = $product->fresh()?->slug;
        $this->assertNotNull($slug);

        $this->assertQuote($slug, 9, '10000.00', null);
        $this->assertQuote($slug, 10, '8000.00', 10);
        $this->assertQuote($slug, 11, '8000.00', 10);
        $this->assertQuote($slug, 49, '8000.00', 10);
        $this->assertQuote($slug, 50, '6000.00', 50);
        $this->assertQuote($slug, 51, '6000.00', 50);

        foreach ([7, 11, 37, 49, 51, 56] as $quantity) {
            $quote = $this->postJson("/api/v1/products/{$slug}/quote", ['quantity' => $quantity])
                ->assertOk();
            $this->assertNull($quote->json('data.purchase_quantity'));
        }
    }

    /**
     * @param  list<array{min_quantity: int, tier_type: string, unit_price: int}>  $tiers
     */
    private function syncProductLevelTiers(Product $product, array $tiers): \Illuminate\Testing\TestResponse
    {
        return $this->putJson('/api/v1/admin/products/'.$product->id.'/price-tiers', [
            'price_tiers' => $tiers,
        ])->assertOk();
    }

    private function assertQuote(string $slug, int $quantity, string $unitPrice, ?int $currentMin): void
    {
        $quote = $this->postJson("/api/v1/products/{$slug}/quote", ['quantity' => $quantity])
            ->assertOk()
            ->assertJsonPath('data.unit_price', $unitPrice);

        if ($currentMin === null) {
            $this->assertNull($quote->json('data.volume_pricing.current_tier'));

            return;
        }

        $quote->assertJsonPath('data.volume_pricing.current_tier.min_quantity', $currentMin);
    }

    private function simpleSellable(float $price): Product
    {
        $product = Product::factory()->tzLocal()->create([
            'price' => $price,
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'minimum_order_quantity' => null,
            'order_increment' => null,
        ]);

        Inventory::query()->firstOrCreate(
            [
                'product_id' => $product->id,
                'product_variant_id' => null,
            ],
            [
                'quantity' => 100,
                'reserved_quantity' => 0,
                'low_stock_threshold' => 2,
            ],
        );

        return $product->fresh() ?? $product;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function editableChinaProduct(array $overrides = []): Product
    {
        $department = Department::factory()->create();
        $category = Category::factory()->forDepartment($department)->create(['parent_id' => null]);
        $subcategory = Category::factory()->forDepartment($department)->create([
            'parent_id' => $category->id,
        ]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'is_active' => true,
        ]);

        return Product::factory()->fromChina()->create(array_merge([
            'catalog_product_type_id' => $catalogType->id,
            'category_id' => $catalogType->subcategory_id,
            'commerce_channel_id' => CommerceChannel::query()->where('code', 'CHINA_IMPORT')->value('id'),
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'is_active' => false,
        ], $overrides));
    }

    private function productTier(Product $product, int $minQuantity, float $unitPrice): void
    {
        ConfigurationPriceTier::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'min_quantity' => $minQuantity,
            'unit_price' => $unitPrice,
        ]);
    }
}

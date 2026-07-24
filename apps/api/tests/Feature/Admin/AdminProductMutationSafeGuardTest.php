<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductLifecycleStatus;
use App\Enums\VariantPriceType;
use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductShippingOption;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductMutationSafeGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(Admin::factory()->create());
    }

    public function test_active_china_variant_price_update_works_when_legacy_shipping_missing(): void
    {
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(22000, 8);
        $this->removeAllShippingOptions($product);

        $price = VariantPrice::query()->where('product_variant_id', $variant->id)->firstOrFail();

        $this->putJson('/api/v1/admin/prices/'.$price->id, [
            'amount' => 23500,
        ])
            ->assertOk()
            ->assertJsonPath('data.amount', 23500);

        $this->assertSame(
            '23500.00',
            (string) VariantPrice::query()->whereKey($price->id)->value('amount'),
        );
    }

    public function test_active_china_sellable_variant_delete_is_blocked_without_shipping_collateral_failure(): void
    {
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(22000, 5);
        $this->removeAllShippingOptions($product);

        $this->deleteJson('/api/v1/admin/products/'.$product->id.'/variants/'.$variant->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['variants'])
            ->assertJsonMissingValidationErrors(['shipping_options']);

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'deleted_at' => null,
        ]);
    }

    public function test_active_china_non_sellable_variant_delete_is_allowed_when_shipping_missing(): void
    {
        $product = Product::factory()->chinaImport()->create([
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
            'price' => 0,
        ]);
        $this->removeAllShippingOptions($product);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'is_default' => true,
            'price' => null,
        ]);

        $this->deleteJson('/api/v1/admin/products/'.$product->id.'/variants/'.$variant->id)
            ->assertOk();

        $this->assertSoftDeleted('product_variants', ['id' => $variant->id]);
    }

    public function test_active_china_shipping_delete_and_disable_are_blocked_when_last_option_removed(): void
    {
        $product = Product::factory()->chinaImport()->create([
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
        ]);
        $this->removeAllShippingOptions($product);

        $option = ProductShippingOption::factory()->air(9000)->create([
            'product_id' => $product->id,
            'is_available' => true,
        ]);

        $this->deleteJson("/api/v1/admin/products/{$product->id}/shipping-options/{$option->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_options']);

        $this->putJson("/api/v1/admin/products/{$product->id}/shipping-options/{$option->id}", [
            'is_available' => false,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_options']);

        $this->putJson("/api/v1/admin/products/{$product->id}/shipping-options/sync", [
            'shipping_options' => [],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_options']);

        $this->assertSame(1, ProductShippingOption::query()->where('product_id', $product->id)->count());
    }

    public function test_draft_china_products_skip_variant_and_shipping_mutation_guards(): void
    {
        $product = Product::factory()->chinaImport()->create([
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'is_active' => false,
        ]);
        $this->removeAllShippingOptions($product);

        $option = ProductShippingOption::factory()->air(7000)->create([
            'product_id' => $product->id,
            'is_available' => true,
        ]);

        $this->deleteJson("/api/v1/admin/products/{$product->id}/shipping-options/{$option->id}")
            ->assertOk();

        ['product' => $variantProduct, 'variant' => $variant] = CatalogCartFixture::purchasable(18000, 3);
        $variantProduct->forceFill([
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'is_active' => false,
        ])->save();
        $this->removeAllShippingOptions($variantProduct);

        $price = VariantPrice::query()->where('product_variant_id', $variant->id)->firstOrFail();
        VariantInventory::query()->where('product_variant_id', $variant->id)->delete();

        $this->deleteJson('/api/v1/admin/prices/'.$price->id)->assertOk();
        $this->deleteJson('/api/v1/admin/products/'.$variantProduct->id.'/variants/'.$variant->id)
            ->assertOk();

        $this->assertSoftDeleted('product_variants', ['id' => $variant->id]);
    }

    private function removeAllShippingOptions(Product $product): void
    {
        ProductShippingOption::withTrashed()
            ->where('product_id', $product->id)
            ->forceDelete();
    }
}

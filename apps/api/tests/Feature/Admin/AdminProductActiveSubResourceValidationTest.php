<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductLifecycleStatus;
use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductShippingOption;
use App\Models\Store;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductActiveSubResourceValidationTest extends TestCase
{
    use RefreshDatabase;
    public function test_active_china_product_cannot_remove_final_shipping_option(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->chinaImport()->create([
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
        ]);

        ProductShippingOption::withTrashed()
            ->where('product_id', $product->id)
            ->forceDelete();

        $option = ProductShippingOption::factory()->air(9000)->create([
            'product_id' => $product->id,
            'is_available' => true,
        ]);

        $this->deleteJson("/api/v1/admin/products/{$product->id}/shipping-options/{$option->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_options']);

        $this->putJson("/api/v1/admin/products/{$product->id}/shipping-options/sync", [
            'shipping_options' => [],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_options']);

        $this->assertSame(1, ProductShippingOption::query()->where('product_id', $product->id)->count());
    }

    public function test_active_tz_product_unaffected_by_shipping_rules(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $store = Store::query()->create([
            'code' => 'TZSUB',
            'name' => 'TZ Sub Resource Store',
            'slug' => 'tz-sub-resource-store',
            'is_active' => true,
        ]);

        $product = Product::factory()->tzLocal()->create([
            'store_id' => $store->id,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
            'name' => 'TZ Active Product',
        ]);

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'name' => 'TZ Active Product Updated',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'TZ Active Product Updated');
    }

    public function test_active_variant_product_cannot_become_variant_less(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(22000, 5);
        $price = VariantPrice::query()->where('product_variant_id', $variant->id)->firstOrFail();
        $inventory = VariantInventory::query()->where('product_variant_id', $variant->id)->firstOrFail();

        $this->deleteJson('/api/v1/admin/products/'.$product->id.'/variants/'.$variant->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['variants']);

        $this->deleteJson('/api/v1/admin/prices/'.$price->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['variants']);

        $this->deleteJson('/api/v1/admin/inventory/'.$inventory->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['variants']);

        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'deleted_at' => null]);
    }

    public function test_draft_products_can_still_be_edited_freely(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->chinaImport()->create([
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'is_active' => false,
        ]);

        ProductShippingOption::withTrashed()
            ->where('product_id', $product->id)
            ->forceDelete();

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

        $this->deleteJson('/api/v1/admin/products/'.$variantProduct->id.'/variants/'.$variant->id)
            ->assertOk();

        $this->assertSoftDeleted('product_variants', ['id' => $variant->id]);
    }
}

<?php

namespace Tests\Feature\ProductShipping;

use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductShippingOption;
use App\Models\User;
use Database\Seeders\ProductTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductShippingOptionEngineTest extends TestCase
{
    use RefreshDatabase;

    private function chinaProduct(bool $clearOptions = true): Product
    {
        $product = Product::factory()->fromChina()->create([
            'fulfillment_source' => 'imported_from_china',
        ]);

        if ($clearOptions) {
            ProductShippingOption::withTrashed()
                ->where('product_id', $product->id)
                ->forceDelete();
            $product->forceFill([
                'air_shipping_price' => null,
                'sea_shipping_price' => null,
            ])->save();
        }

        return $product->fresh();
    }

    public function test_admin_can_crud_shipping_options(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = $this->chinaProduct();

        $created = $this->postJson("/api/v1/admin/products/{$product->id}/shipping-options", [
            'transport_mode' => 'air',
            'price' => 12000,
            'currency' => 'TZS',
            'is_available' => true,
            'notes' => 'Express air',
        ])->assertCreated()
            ->assertJsonPath('data.transport_mode', 'air')
            ->assertJsonPath('data.price', '12000.00');

        $optionId = $created->json('data.id');

        $this->getJson("/api/v1/admin/products/{$product->id}/shipping-options")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->putJson("/api/v1/admin/products/{$product->id}/shipping-options/{$optionId}", [
            'price' => 15000,
            'is_available' => false,
        ])->assertOk()
            ->assertJsonPath('data.price', '15000.00')
            ->assertJsonPath('data.is_available', false);

        $product->refresh();
        $this->assertNull($product->air_shipping_price);

        $this->deleteJson("/api/v1/admin/products/{$product->id}/shipping-options/{$optionId}")
            ->assertOk();

        $this->assertSoftDeleted('product_shipping_options', ['id' => $optionId]);

        $this->postJson("/api/v1/admin/products/{$product->id}/shipping-options/{$optionId}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $optionId);
    }

    public function test_duplicate_transport_mode_rejected(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = $this->chinaProduct();

        $this->postJson("/api/v1/admin/products/{$product->id}/shipping-options", [
            'transport_mode' => 'sea',
            'price' => 4000,
        ])->assertCreated();

        $this->postJson("/api/v1/admin/products/{$product->id}/shipping-options", [
            'transport_mode' => 'sea',
            'price' => 5000,
        ])->assertStatus(422);
    }

    public function test_sync_replaces_options_and_legacy_columns(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = $this->chinaProduct();

        $this->putJson("/api/v1/admin/products/{$product->id}/shipping-options/sync", [
            'shipping_options' => [
                [
                    'transport_mode' => 'air',
                    'price' => 9000,
                    'is_available' => true,
                ],
                [
                    'transport_mode' => 'sea',
                    'price' => 3500,
                    'is_available' => true,
                ],
            ],
        ])->assertOk()->assertJsonCount(2, 'data');

        $product->refresh();
        $this->assertSame('9000.00', (string) $product->air_shipping_price);
        $this->assertSame('3500.00', (string) $product->sea_shipping_price);
        $this->assertSame(2, $product->shippingOptions()->count());
    }

    public function test_relationships(): void
    {
        $product = $this->chinaProduct();
        $option = ProductShippingOption::factory()->air(8000)->create([
            'product_id' => $product->id,
        ]);

        $this->assertTrue($product->shippingOptions()->whereKey($option->id)->exists());
        $this->assertTrue($option->product()->is($product));
    }

    public function test_unavailable_option_hidden_from_customer_prices(): void
    {
        $product = $this->chinaProduct();
        ProductShippingOption::factory()->air(10000)->create([
            'product_id' => $product->id,
            'is_available' => true,
        ]);
        ProductShippingOption::factory()->sea(4000)->unavailable()->create([
            'product_id' => $product->id,
        ]);

        app(\App\Services\ProductShipping\ProductShippingOptionEngine::class)
            ->syncLegacyColumns($product->fresh());

        $product->refresh();
        $this->assertSame('10000.00', $product->shippingPriceForMethod('air'));
        $this->assertNull($product->shippingPriceForMethod('sea'));
    }

    public function test_create_product_with_shipping_options_payload(): void
    {
        $this->seed(ProductTypeSeeder::class);
        Sanctum::actingAs(Admin::factory()->create());

        $category = \App\Models\Category::factory()->create();
        $cpt = \App\Models\CatalogProductType::factory()->create([
            'subcategory_id' => $category->id,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/admin/products', [
            'name' => 'Shipping Options Phone',
            'category_id' => $category->id,
            'catalog_product_type_id' => $cpt->id,
            'price' => 200000,
            'status' => true,
            'stock_quantity' => 3,
            'shipping_options' => [
                [
                    'transport_mode' => 'air',
                    'price' => 11000,
                    'currency' => 'TZS',
                    'is_available' => true,
                    'notes' => 'Air only SKU',
                ],
            ],
        ]);

        $response->assertCreated();
        $productId = $response->json('data.id');

        $this->assertDatabaseHas('product_shipping_options', [
            'product_id' => $productId,
            'transport_mode' => 'air',
            'is_available' => 1,
        ]);

        $product = Product::query()->findOrFail($productId);
        $this->assertSame('11000.00', (string) $product->air_shipping_price);
        $this->assertNull($product->sea_shipping_price);
    }

    public function test_ownership_nested_option_belongs_to_product(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $productA = $this->chinaProduct();
        $productB = $this->chinaProduct();
        $option = ProductShippingOption::factory()->air()->create([
            'product_id' => $productA->id,
        ]);

        $this->putJson("/api/v1/admin/products/{$productB->id}/shipping-options/{$option->id}", [
            'price' => 1,
        ])->assertNotFound();

        $this->getJson("/api/v1/admin/products/{$productB->id}/shipping-options/{$option->id}")
            ->assertNotFound();
    }

    public function test_guest_cannot_manage_shipping_options(): void
    {
        $product = $this->chinaProduct();

        $this->postJson("/api/v1/admin/products/{$product->id}/shipping-options", [
            'transport_mode' => 'air',
            'price' => 1000,
        ])->assertUnauthorized();
    }

    public function test_customer_token_rejected_on_admin_shipping_options(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = $this->chinaProduct();

        $this->getJson("/api/v1/admin/products/{$product->id}/shipping-options")
            ->assertUnauthorized();
    }

    public function test_soft_delete_keeps_row(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = $this->chinaProduct();
        $option = ProductShippingOption::factory()->sea()->create([
            'product_id' => $product->id,
        ]);

        $this->deleteJson("/api/v1/admin/products/{$product->id}/shipping-options/{$option->id}")
            ->assertOk();

        $this->assertNotNull(ProductShippingOption::withTrashed()->find($option->id)?->deleted_at);
        $this->assertSame(0, ProductShippingOption::query()->where('product_id', $product->id)->count());
    }

    public function test_from_china_factory_backfills_options(): void
    {
        $product = $this->chinaProduct(clearOptions: false);

        $this->assertGreaterThanOrEqual(1, $product->shippingOptions()->count());
        $this->assertNotNull($product->shippingPriceForMethod('air'));
    }
}

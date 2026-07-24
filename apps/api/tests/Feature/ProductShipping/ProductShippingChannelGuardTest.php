<?php

namespace Tests\Feature\ProductShipping;

use App\Models\Admin;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Product;
use App\Models\Store;
use Database\Seeders\ProductTypeSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductShippingChannelGuardTest extends TestCase
{
    private function tzLocalProduct(): Product
    {
        $store = Store::query()->create([
            'code' => 'TZGUARD',
            'name' => 'TZ Guard Store',
            'slug' => 'tz-guard-store',
            'is_active' => true,
        ]);

        return Product::factory()->tzLocal()->create([
            'store_id' => $store->id,
        ]);
    }

    public function test_china_import_can_create_shipping_option(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = Product::factory()->chinaImport()->create();

        $this->postJson("/api/v1/admin/products/{$product->id}/shipping-options", [
            'transport_mode' => 'air',
            'price' => 12000,
        ])->assertCreated()
            ->assertJsonPath('data.transport_mode', 'air');
    }

    public function test_china_import_can_sync_shipping_options(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = Product::factory()->chinaImport()->create();

        $this->putJson("/api/v1/admin/products/{$product->id}/shipping-options/sync", [
            'shipping_options' => [
                [
                    'transport_mode' => 'air',
                    'price' => 9000,
                    'is_available' => true,
                ],
            ],
        ])->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_tz_local_cannot_create_shipping_option(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = $this->tzLocalProduct();

        $this->postJson("/api/v1/admin/products/{$product->id}/shipping-options", [
            'transport_mode' => 'air',
            'price' => 12000,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['shipping']);
    }

    public function test_tz_local_cannot_sync_shipping_options(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = $this->tzLocalProduct();

        $this->putJson("/api/v1/admin/products/{$product->id}/shipping-options/sync", [
            'shipping_options' => [
                [
                    'transport_mode' => 'air',
                    'price' => 9000,
                    'is_available' => true,
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['shipping']);
    }

    public function test_tz_local_cannot_submit_air_shipping_price_on_update(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = $this->tzLocalProduct();

        $this->putJson("/api/v1/admin/products/{$product->id}", [
            'air_shipping_price' => 8000,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['air_shipping_price']);
    }

    public function test_tz_local_cannot_submit_sea_shipping_price_on_update(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = $this->tzLocalProduct();

        $this->putJson("/api/v1/admin/products/{$product->id}", [
            'sea_shipping_price' => 4500,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['sea_shipping_price']);
    }

    public function test_tz_local_cannot_submit_shipping_options_on_legacy_product_update(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $product = $this->tzLocalProduct();

        $this->putJson("/api/v1/admin/products/{$product->id}", [
            'shipping_options' => [
                [
                    'transport_mode' => 'air',
                    'price' => 11000,
                    'is_available' => true,
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_options']);
    }

    public function test_tz_local_cannot_submit_freight_on_create(): void
    {
        $this->seed(ProductTypeSeeder::class);
        Sanctum::actingAs(Admin::factory()->create());

        $category = Category::factory()->create();
        $cpt = \App\Models\CatalogProductType::factory()->create([
            'subcategory_id' => $category->id,
            'is_active' => true,
        ]);
        $store = Store::query()->create([
            'code' => 'TZNEW',
            'name' => 'TZ New Store',
            'slug' => 'tz-new-store',
            'is_active' => true,
        ]);
        $tzChannelId = CommerceChannel::query()->where('code', 'TZ_LOCAL')->value('id');

        $this->postJson('/api/v1/admin/products', [
            'name' => 'Local Guard Product',
            'category_id' => $category->id,
            'catalog_product_type_id' => $cpt->id,
            'commerce_channel_id' => $tzChannelId,
            'store_id' => $store->id,
            'price' => 15000,
            'stock_quantity' => 2,
            'air_shipping_price' => 8000,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['air_shipping_price']);
    }
}

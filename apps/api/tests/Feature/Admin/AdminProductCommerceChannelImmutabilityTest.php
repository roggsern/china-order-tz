<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductLifecycleStatus;
use App\Models\Admin;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductCommerceChannelImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_china_product_cannot_switch_to_tz_local(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $china = CommerceChannel::query()->where('code', 'CHINA_IMPORT')->firstOrFail();
        $tz = CommerceChannel::query()->where('code', 'TZ_LOCAL')->firstOrFail();
        $store = $this->makeStore();
        $product = $this->makeProductOnChannel($china);

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'commerce_channel_id' => $tz->id,
            'store_id' => $store->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commerce_channel_id'])
            ->assertJsonFragment([
                'commerce_channel_id' => ['Commerce channel cannot be changed after product creation.'],
            ]);

        $fresh = $product->fresh();
        $this->assertSame($china->id, $fresh?->commerce_channel_id);
        $this->assertSame('imported_from_china', $fresh?->fulfillment_source);
    }

    public function test_tz_local_product_cannot_switch_to_china_import(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $china = CommerceChannel::query()->where('code', 'CHINA_IMPORT')->firstOrFail();
        $tz = CommerceChannel::query()->where('code', 'TZ_LOCAL')->firstOrFail();
        $store = $this->makeStore();
        $product = $this->makeProductOnChannel($tz, ['store_id' => $store->id]);

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'commerce_channel_id' => $china->id,
            'air_shipping_price' => 5000,
            'sea_shipping_price' => 2500,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commerce_channel_id']);

        $fresh = $product->fresh();
        $this->assertSame($tz->id, $fresh?->commerce_channel_id);
        $this->assertSame('buy_from_tz', $fresh?->fulfillment_source);
        $this->assertSame($store->id, $fresh?->store_id);
    }

    public function test_update_without_channel_change_still_works(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $china = CommerceChannel::query()->where('code', 'CHINA_IMPORT')->firstOrFail();
        $product = $this->makeProductOnChannel($china, [
            'name' => 'Original Name',
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'is_active' => false,
        ]);

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'name' => 'Renamed China Product',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Renamed China Product');

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'commerce_channel_id' => $china->id,
            'name' => 'Resubmitted Same Channel',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Resubmitted Same Channel')
            ->assertJsonPath('data.commerce_channel_id', $china->id);
    }

    public function test_create_flow_unchanged(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $china = CommerceChannel::query()->where('code', 'CHINA_IMPORT')->firstOrFail();
        $tz = CommerceChannel::query()->where('code', 'TZ_LOCAL')->firstOrFail();
        $store = $this->makeStore();
        $category = Category::factory()->create();
        $catalogProductType = CatalogProductType::factory()->create([
            'subcategory_id' => $category->id,
            'is_active' => true,
        ]);

        $chinaResponse = $this->postJson('/api/v1/admin/products', [
            'name' => 'Immutable Create China',
            'category_id' => $category->id,
            'catalog_product_type_id' => $catalogProductType->id,
            'commerce_channel_id' => $china->id,
            'price' => 10000,
            'stock_quantity' => 1,
            'lifecycle_status' => 'draft',
        ])->assertCreated();

        $this->assertSame($china->id, $chinaResponse->json('data.commerce_channel_id'));

        $tzResponse = $this->postJson('/api/v1/admin/products', [
            'name' => 'Immutable Create TZ',
            'category_id' => $category->id,
            'catalog_product_type_id' => $catalogProductType->id,
            'commerce_channel_id' => $tz->id,
            'store_id' => $store->id,
            'price' => 12000,
            'stock_quantity' => 1,
            'lifecycle_status' => 'draft',
        ])->assertCreated();

        $this->assertSame($tz->id, $tzResponse->json('data.commerce_channel_id'));
    }

    private function makeStore(): Store
    {
        return Store::query()->create([
            'code' => 'TZ'.strtoupper(substr((string) str()->uuid(), 0, 4)),
            'name' => 'Immutable Test Store',
            'slug' => 'immutable-test-store-'.str()->random(8),
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProductOnChannel(CommerceChannel $channel, array $overrides = []): Product
    {
        $category = Category::factory()->create();
        $catalogProductType = CatalogProductType::factory()->create([
            'subcategory_id' => $category->id,
            'is_active' => true,
        ]);

        return Product::factory()->create(array_merge([
            'name' => 'Channel Immutable Product',
            'category_id' => $category->id,
            'catalog_product_type_id' => $catalogProductType->id,
            'commerce_channel_id' => $channel->id,
            'fulfillment_source' => $channel->code === 'TZ_LOCAL'
                ? 'buy_from_tz'
                : 'imported_from_china',
            'price' => 15000,
        ], $overrides));
    }
}

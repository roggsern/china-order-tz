<?php

namespace Tests\Feature\Catalog;

use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Product;
use App\Models\Store;
use Database\Factories\Support\CatalogCartFixture;
use Database\Seeders\CommerceChannelSeeder;
use Database\Seeders\StoreSeeder;
use Database\Seeders\TzStoreCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADMIN-12.12H — product card availability alignment.
 */
class CustomerProductCardAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_card_exposes_unavailable_product_availability_fields(): void
    {
        // China simple products intentionally skip inventory-policy gating; use TZ_LOCAL
        // without inventory so the card remains unavailable for missing policy.
        $this->seed(CommerceChannelSeeder::class);
        $this->seed(StoreSeeder::class);
        $this->seed(TzStoreCategorySeeder::class);

        $store = Store::query()->where('slug', 'zion-mode')->firstOrFail();
        $category = Category::query()
            ->where('store_id', $store->id)
            ->where('name', 'Dresses')
            ->firstOrFail();
        $tz = CommerceChannel::query()
            ->where('code', CommerceChannelCode::TzLocal->value)
            ->firstOrFail();

        $product = Product::factory()->create([
            'slug' => 'card-unavailable-product',
            'store_id' => $store->id,
            'category_id' => $category->id,
            'commerce_channel_id' => $tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'is_demo' => false,
            'price' => 15000,
        ]);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.0.slug', $product->slug)
            ->assertJsonPath('data.0.is_purchasable', false)
            ->assertJsonPath('data.0.availability_status', 'unavailable')
            ->assertJsonPath('data.0.unavailability_reason', 'missing_inventory_policy');
    }

    public function test_listing_card_exposes_out_of_stock_purchasable_product(): void
    {
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(22000, 0);
        $product->update(['slug' => 'card-oos-product']);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.0.slug', $product->slug)
            ->assertJsonPath('data.0.is_purchasable', true)
            ->assertJsonPath('data.0.availability_status', 'out_of_stock')
            ->assertJsonMissingPath('data.0.unavailability_reason')
            ->assertJsonPath('data.0.variants.0.id', $variant->id)
            ->assertJsonPath('data.0.variants.0.stock', 0)
            ->assertJsonPath('data.0.variants.0.in_stock', false);
    }

    public function test_listing_card_exposes_available_purchasable_product(): void
    {
        ['product' => $product] = CatalogCartFixture::purchasable(22000, 5);
        $product->update(['slug' => 'card-available-product']);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.0.slug', $product->slug)
            ->assertJsonPath('data.0.is_purchasable', true)
            ->assertJsonPath('data.0.availability_status', 'available')
            ->assertJsonPath('data.0.requires_variant_selection', true)
            ->assertJsonMissingPath('data.0.unavailability_reason');
    }

    public function test_listing_card_simple_product_does_not_require_variant_selection(): void
    {
        $this->seed(CommerceChannelSeeder::class);
        $this->seed(\Database\Seeders\CategorySeeder::class);

        $china = CommerceChannel::query()
            ->where('code', CommerceChannelCode::ChinaImport->value)
            ->firstOrFail();
        $phones = Category::query()->where('slug', 'electronics-phones')->firstOrFail();

        $product = Product::factory()->create([
            'slug' => 'card-simple-no-variant',
            'category_id' => $phones->id,
            'store_id' => null,
            'commerce_channel_id' => $china->id,
            'fulfillment_source' => CommerceChannelCode::ChinaImport->fulfillmentSource(),
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'is_demo' => false,
            'price' => 45000,
        ]);

        \App\Models\ProductShippingOption::factory()->air(9000)->create([
            'product_id' => $product->id,
            'is_available' => true,
        ]);

        \App\Models\ChinaCommercialStock::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'available_quantity' => 8,
            'reserved_quantity' => 0,
            'ordered_quantity' => 0,
        ]);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.0.slug', $product->slug)
            ->assertJsonPath('data.0.is_purchasable', true)
            ->assertJsonPath('data.0.availability_status', 'available')
            ->assertJsonPath('data.0.requires_variant_selection', false);
    }

    public function test_listing_card_unavailable_variant_path_still_requires_selection(): void
    {
        ['product' => $product] = CatalogCartFixture::purchasable(22000, 5);
        $product->update([
            'slug' => 'card-unavailable-variant-path',
            'lifecycle_status' => ProductLifecycleStatus::OutOfStock,
        ]);

        // OutOfStock lifecycle is storefront-visible but not listing-purchasable.
        $this->getJson("/api/v1/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.slug', $product->slug)
            ->assertJsonPath('data.is_purchasable', false)
            ->assertJsonPath('data.availability_status', 'unavailable')
            ->assertJsonPath('data.requires_variant_selection', true);
    }

    public function test_listing_query_results_unchanged_except_availability_fields(): void
    {
        $visible = Product::factory()->create([
            'slug' => 'listing-scope-visible',
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'is_demo' => false,
            'price' => 12000,
        ]);

        Product::factory()->create([
            'slug' => 'listing-scope-draft-hidden',
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'visibility' => ProductVisibility::Public,
            'is_demo' => false,
            'price' => 9000,
        ]);

        $response = $this->getJson('/api/v1/products')->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug')->all();

        $this->assertContains($visible->slug, $slugs);
        $this->assertNotContains('listing-scope-draft-hidden', $slugs);
        $this->assertArrayHasKey('is_purchasable', $response->json('data.0'));
        $this->assertArrayHasKey('availability_status', $response->json('data.0'));
    }
}

<?php

namespace Tests\Feature\Catalog;

use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Models\Product;
use Database\Factories\Support\CatalogCartFixture;
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
        $product = Product::factory()->create([
            'slug' => 'card-unavailable-product',
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
            ->assertJsonMissingPath('data.0.unavailability_reason');
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

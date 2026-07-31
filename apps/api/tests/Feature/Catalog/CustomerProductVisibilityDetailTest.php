<?php

namespace Tests\Feature\Catalog;

use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * ADMIN-12.12G — visible product detail with purchasability state.
 */
class CustomerProductVisibilityDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_visible_non_purchasable_product_returns_detail_successfully(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'is_demo' => false,
            'price' => 15000,
        ]);

        $this->getJson("/api/v1/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', $product->slug)
            ->assertJsonPath('data.is_purchasable', false)
            ->assertJsonPath('data.availability_status', 'unavailable')
            ->assertJsonPath('data.unavailability_reason', 'missing_inventory_policy');

        $this->getJson("/api/v1/products/{$product->slug}/configuration")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_purchasable', false)
            ->assertJsonPath('data.availability_status', 'unavailable')
            ->assertJsonPath('data.unavailability_reason', 'missing_inventory_policy');
    }

    public function test_non_visible_product_still_returns_404(): void
    {
        $draft = Product::factory()->create([
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'visibility' => ProductVisibility::Public,
            'is_demo' => false,
            'price' => 15000,
        ]);

        Inventory::query()->firstOrCreate(
            ['product_id' => $draft->id, 'product_variant_id' => null],
            ['quantity' => 5, 'reserved_quantity' => 0, 'low_stock_threshold' => 1],
        );

        $this->getJson("/api/v1/products/{$draft->slug}")
            ->assertNotFound();

        $this->getJson("/api/v1/products/{$draft->slug}/configuration")
            ->assertNotFound();

        $hidden = Product::factory()->create([
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Hidden,
            'is_demo' => false,
            'price' => 15000,
        ]);

        Inventory::query()->firstOrCreate(
            ['product_id' => $hidden->id, 'product_variant_id' => null],
            ['quantity' => 5, 'reserved_quantity' => 0, 'low_stock_threshold' => 1],
        );

        $this->getJson("/api/v1/products/{$hidden->slug}")
            ->assertNotFound();
    }

    public function test_purchasable_product_detail_is_unchanged(): void
    {
        ['product' => $product] = CatalogCartFixture::purchasable(22000, 5);

        $this->getJson("/api/v1/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.is_purchasable', true)
            ->assertJsonPath('data.availability_status', 'available')
            ->assertJsonMissingPath('data.unavailability_reason')
            ->assertJsonPath('data.slug', $product->slug)
            ->assertJsonPath('data.price', '22000.00');
    }

    public function test_cart_still_rejects_non_purchasable_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'is_demo' => false,
            'price' => 15000,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.is_purchasable', false);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id']);
    }
}

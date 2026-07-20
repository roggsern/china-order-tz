<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\ConfigurationPriceTier;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductPricingQuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_quote_price_with_breakdown(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $type = ProductType::query()->create([
            'name' => 'Phones',
            'slug' => 'phones-quote',
            'has_configurations' => true,
            'allows_price_override' => true,
            'allows_moq_pricing' => true,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'product_type_id' => $type->id,
            'price' => 500000,
            'sku' => 'QUOTE-BASE',
        ]);

        $config = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'QUOTE-CFG-1',
            'name' => 'Silver / 128GB',
            'price' => 520000,
        ]);

        ConfigurationPriceTier::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $config->id,
            'min_quantity' => 10,
            'unit_price' => 480000,
        ]);

        $response = $this->postJson("/api/v1/admin/products/{$product->id}/quote", [
            'configuration_id' => $config->id,
            'quantity' => 12,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.configuration_id', $config->id)
            ->assertJsonPath('data.quantity', 12)
            ->assertJsonPath('data.currency', 'TZS')
            ->assertJsonPath('data.unit_price', '480000.00')
            ->assertJsonPath('data.line_total', '5760000.00')
            ->assertJsonPath('data.breakdown.0.stage', 'base')
            ->assertJsonPath('data.breakdown.1.stage', 'configuration_override')
            ->assertJsonPath('data.breakdown.2.stage', 'quantity_tier')
            ->assertJsonPath('data.breakdown.3.stage', 'promotion')
            ->assertJsonPath('data.breakdown.3.applied', false)
            ->assertJsonPath('data.breakdown.4.stage', 'coupon')
            ->assertJsonPath('data.breakdown.5.stage', 'customer_group')
            ->assertJsonPath('data.breakdown.6.stage', 'final');
    }

    public function test_admin_can_sync_configuration_price_tiers(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->create(['price' => 100000]);
        $config = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 110000,
        ]);

        $response = $this->putJson("/api/v1/admin/products/{$product->id}/price-tiers", [
            'configuration_id' => $config->id,
            'price_tiers' => [
                ['min_quantity' => 1, 'unit_price' => 110000],
                ['min_quantity' => 10, 'unit_price' => 100000],
                ['min_quantity' => 50, 'unit_price' => 90000],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');

        $this->assertDatabaseHas('configuration_price_tiers', [
            'product_id' => $product->id,
            'product_variant_id' => $config->id,
            'min_quantity' => 50,
            'unit_price' => '90000.00',
        ]);
    }

    public function test_quote_rejects_configuration_from_another_product(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->create();
        $other = Product::factory()->create();
        $foreignConfig = ProductVariant::factory()->create([
            'product_id' => $other->id,
        ]);

        $this->postJson("/api/v1/admin/products/{$product->id}/quote", [
            'configuration_id' => $foreignConfig->id,
            'quantity' => 1,
        ])->assertStatus(422);
    }
}

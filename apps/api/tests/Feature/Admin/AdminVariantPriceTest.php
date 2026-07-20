<?php

namespace Tests\Feature\Admin;

use App\Enums\VariantPriceType;
use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminVariantPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_variant_prices_with_scheduling_and_currencies(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->create([
            'name' => 'Pricing Demo Phone',
            'slug' => 'pricing-demo-phone',
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => '256GB Black',
            'sku' => 'PRICE-DEMO-256',
            'price' => null,
            'is_default' => true,
        ]);

        $this->assertTrue(Schema::hasTable('variant_prices'));
        $this->assertTrue($variant->prices()->doesntExist());

        $list = $this->getJson('/api/v1/admin/variants/'.$variant->id.'/prices');
        $list->assertOk()->assertJsonPath('data', []);

        $retailTzs = $this->postJson('/api/v1/admin/variants/'.$variant->id.'/prices', [
            'price_type' => 'retail',
            'currency' => 'tzs',
            'amount' => 1850000,
            'compare_at_price' => 1990000,
            'cost_price' => 1200000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);

        $retailTzs->assertCreated()
            ->assertJsonPath('data.price_type', 'retail')
            ->assertJsonPath('data.currency', 'TZS')
            ->assertJsonPath('data.amount', 1850000)
            ->assertJsonPath('data.is_currently_active', true);

        $priceId = $retailTzs->json('data.id');

        $wholesaleUsd = $this->postJson('/api/v1/admin/variants/'.$variant->id.'/prices', [
            'price_type' => VariantPriceType::Wholesale->value,
            'currency' => 'USD',
            'amount' => 620,
            'minimum_quantity' => 5,
            'is_active' => true,
            'starts_at' => now()->subDay()->toIso8601String(),
            'ends_at' => now()->addMonth()->toIso8601String(),
        ]);
        $wholesaleUsd->assertCreated()
            ->assertJsonPath('data.price_type', 'wholesale')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.minimum_quantity', 5)
            ->assertJsonPath('data.is_currently_active', true);

        $futureVip = $this->postJson('/api/v1/admin/variants/'.$variant->id.'/prices', [
            'price_type' => 'vip',
            'currency' => 'USD',
            'amount' => 550,
            'starts_at' => now()->addWeek()->toIso8601String(),
            'ends_at' => now()->addMonths(2)->toIso8601String(),
            'is_active' => true,
        ]);
        $futureVip->assertCreated()
            ->assertJsonPath('data.is_currently_active', false);

        $this->getJson('/api/v1/admin/variants/'.$variant->id.'/prices')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->assertNotNull($variant->fresh()->retailPrice('TZS'));
        $this->assertSame('1850000.00', (string) $variant->fresh()->retailPrice('TZS')->amount);
        $this->assertNotNull($variant->fresh()->wholesalePrice('USD'));
        $this->assertCount(2, $variant->fresh()->activePrices());

        $updated = $this->putJson('/api/v1/admin/prices/'.$priceId, [
            'amount' => 1799000,
            'is_active' => false,
        ]);
        $updated->assertOk()
            ->assertJsonPath('data.amount', 1799000)
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.is_currently_active', false);

        $this->assertNull($variant->fresh()->retailPrice('TZS'));

        $reactivated = $this->putJson('/api/v1/admin/prices/'.$priceId, [
            'is_active' => true,
        ]);
        $reactivated->assertOk()->assertJsonPath('data.is_currently_active', true);

        $this->deleteJson('/api/v1/admin/prices/'.$priceId)->assertOk();
        $this->assertSoftDeleted('variant_prices', ['id' => $priceId]);

        $this->postJson('/api/v1/admin/variants/'.$variant->id.'/prices', [
            'price_type' => 'retail',
            'currency' => 'XXX',
            'amount' => -10,
        ])->assertStatus(422);

        $this->postJson('/api/v1/admin/variants/'.$variant->id.'/prices', [
            'price_type' => 'flash',
            'currency' => 'TZS',
            'amount' => 100,
        ])->assertStatus(422);
    }
}

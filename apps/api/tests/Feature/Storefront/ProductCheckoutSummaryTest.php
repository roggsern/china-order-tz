<?php

namespace Tests\Feature\Storefront;

use App\Enums\ProductVisibility;
use Database\Factories\Support\CatalogCartFixture;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProductCheckoutSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Cache::flush();
    }

    public function test_checkout_summary_returns_card_shape_without_pdp_graphs(): void
    {
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::chinaPurchasable(28000, 6);
        $product->forceFill([
            'slug' => 'checkout-summary-blazer',
            'visibility' => ProductVisibility::Public,
            'store_id' => null,
            'description' => str_repeat('PDP description should not appear. ', 40),
        ])->save();

        $summary = $this->getJson('/api/v1/products/checkout-summary-blazer/checkout-summary')
            ->assertOk()
            ->json('data');

        $this->assertSame('checkout-summary-blazer', $summary['slug'] ?? null);
        $this->assertSame($product->id, $summary['id'] ?? null);
        $this->assertArrayHasKey('is_purchasable', $summary);
        $this->assertArrayHasKey('price', $summary);
        $this->assertArrayHasKey('shipping_prices', $summary);
        $this->assertArrayNotHasKey('description', $summary);
        $this->assertArrayNotHasKey('images', $summary);
        $this->assertArrayNotHasKey('videos', $summary);
        $this->assertArrayNotHasKey('specifications', $summary);
        $this->assertArrayNotHasKey('configurations', $summary);

        $this->assertSame($variant->id, $summary['variants'][0]['id'] ?? null);
        $this->assertArrayNotHasKey('images', $summary['variants'][0] ?? []);
        $this->assertArrayNotHasKey('display_attributes', $summary['variants'][0] ?? []);
        $this->assertArrayNotHasKey('attribute_values', $summary['variants'][0] ?? []);
    }

    public function test_checkout_summary_is_much_smaller_than_pdp_show(): void
    {
        ['product' => $product] = CatalogCartFixture::chinaPurchasable(32000, 4);
        $product->forceFill([
            'slug' => 'checkout-summary-size-compare',
            'visibility' => ProductVisibility::Public,
            'store_id' => null,
            'description' => str_repeat('Heavy PDP copy for size comparison. ', 80),
        ])->save();

        $summaryBytes = strlen(
            $this->getJson('/api/v1/products/checkout-summary-size-compare/checkout-summary')
                ->assertOk()
                ->getContent(),
        );
        $pdpBytes = strlen(
            $this->getJson('/api/v1/products/checkout-summary-size-compare')
                ->assertOk()
                ->getContent(),
        );

        $this->assertLessThan($pdpBytes, $summaryBytes);
        $this->assertLessThan((int) ($pdpBytes * 0.6), $summaryBytes);
    }
}

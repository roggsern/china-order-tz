<?php

namespace Tests\Feature\Storefront;

use App\Enums\ProductVisibility;
use Database\Factories\Support\CatalogCartFixture;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ChinaProductListingPayloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Cache::flush();
    }

    public function test_listing_variants_omit_media_and_attribute_graphs(): void
    {
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::chinaPurchasable(28000, 6);
        $product->forceFill([
            'slug' => 'slim-listing-variant-phone',
            'visibility' => ProductVisibility::Public,
            'store_id' => null,
        ])->save();

        $card = $this->getJson('/api/v1/storefront/china/products?per_page=12')
            ->assertOk()
            ->json('data.0');

        $this->assertSame('slim-listing-variant-phone', $card['slug'] ?? null);
        $this->assertSame($variant->id, $card['variants'][0]['id'] ?? null);
        $this->assertSame(6, $card['variants'][0]['stock'] ?? null);
        $this->assertTrue($card['variants'][0]['in_stock'] ?? false);
        $this->assertArrayNotHasKey('images', $card['variants'][0] ?? []);
        $this->assertArrayNotHasKey('primary_image', $card['variants'][0] ?? []);
        $this->assertArrayNotHasKey('display_attributes', $card['variants'][0] ?? []);
        $this->assertArrayNotHasKey('attribute_values', $card['variants'][0] ?? []);
        $this->assertArrayNotHasKey('price', $card['variants'][0] ?? []);
    }
}

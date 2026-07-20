<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use Database\Seeders\BrandSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\Iphone16ProDemoSeeder;
use Database\Seeders\ProductTypeSeeder;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Iphone16ProConfigurationDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_iphone_16_pro_demo_exposes_dependency_aware_configurations_and_quotes(): void
    {
        $this->seed([
            ProductTypeSeeder::class,
            CategorySeeder::class,
            BrandSeeder::class,
            SupplierSeeder::class,
            Iphone16ProDemoSeeder::class,
        ]);

        $product = Product::query()->where('slug', Iphone16ProDemoSeeder::SLUG)->firstOrFail();

        $this->assertSame('phones', $product->productType?->slug);
        $this->assertCount(8, $product->variants);

        $empty = $this->getJson("/api/v1/products/{$product->slug}/configuration");
        $empty->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_configurations', true)
            ->assertJsonPath('data.product_type.slug', 'phones');

        $this->assertCount(8, $empty->json('data.configurations'));

        $attributes = collect($empty->json('data.attributes'));
        $color = $attributes->firstWhere('slug', 'color');
        $storage = $attributes->firstWhere('slug', 'storage');

        $this->assertNotNull($color);
        $this->assertNotNull($storage);
        $this->assertTrue($color['participates_in_configuration']);
        $this->assertTrue($storage['participates_in_configuration']);

        $silver = collect($color['values'])->firstWhere('slug', 'silver');
        $black = collect($color['values'])->firstWhere('slug', 'black');
        $blue = collect($color['values'])->firstWhere('slug', 'blue');
        $storageBySlug = collect($storage['values'])->keyBy('slug');

        $this->assertNotNull($silver);
        $this->assertNotNull($black);
        $this->assertNotNull($blue);

        // Black → only 128 / 256 allowed in cascading options.
        $blackSelected = $this->getJson(
            "/api/v1/products/{$product->slug}/configuration?".http_build_query([
                "selections[{$color['id']}]" => $black['id'],
            ])
        )->assertOk();

        $allowedStorage = $blackSelected->json("data.allowed_value_ids.{$storage['id']}");
        $this->assertEqualsCanonicalizing(
            [$storageBySlug['128gb']['id'], $storageBySlug['256gb']['id']],
            $allowedStorage,
        );

        // Blue → only 256 / 512.
        $blueSelected = $this->getJson(
            "/api/v1/products/{$product->slug}/configuration?".http_build_query([
                "selections[{$color['id']}]" => $blue['id'],
            ])
        )->assertOk();

        $allowedBlueStorage = $blueSelected->json("data.allowed_value_ids.{$storage['id']}");
        $this->assertEqualsCanonicalizing(
            [$storageBySlug['256gb']['id'], $storageBySlug['512gb']['id']],
            $allowedBlueStorage,
        );

        $oos = collect($empty->json('data.configurations'))
            ->first(fn (array $row) => $row['stock'] === 0 && str_contains(strtolower($row['name']), '1tb'));

        $this->assertNotNull($oos);
        $this->assertFalse($oos['in_stock']);

        // Silver 1TB exists but is excluded from allowed options while out of stock.
        $silverSelected = $this->getJson(
            "/api/v1/products/{$product->slug}/configuration?".http_build_query([
                "selections[{$color['id']}]" => $silver['id'],
            ])
        )->assertOk();

        $allowedSilverStorage = $silverSelected->json("data.allowed_value_ids.{$storage['id']}");
        $this->assertNotContains($storageBySlug['1tb']['id'], $allowedSilverStorage);
        $this->assertContains($storageBySlug['128gb']['id'], $allowedSilverStorage);
        $black256 = collect($empty->json('data.configurations'))
            ->first(fn (array $row) => str_contains(strtolower($row['name']), 'black')
                && str_contains(strtolower($row['name']), '256'));

        $this->assertNotNull($black256);

        $quoteQty1 = $this->postJson("/api/v1/products/{$product->slug}/quote", [
            'configuration_id' => $black256['id'],
            'quantity' => 1,
        ])->assertOk();

        $quoteQty3 = $this->postJson("/api/v1/products/{$product->slug}/quote", [
            'configuration_id' => $black256['id'],
            'quantity' => 3,
        ])->assertOk();

        $this->assertSame('3899000.00', $quoteQty1->json('data.unit_price'));
        $this->assertSame('3749000.00', $quoteQty3->json('data.unit_price'));
        $this->assertNotEmpty($black256['sku']);
    }
}

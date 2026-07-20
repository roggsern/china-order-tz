<?php

namespace Tests\Feature\Catalog;

use App\Enums\VariantPriceType;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductType;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use Database\Seeders\ProductTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerProductConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_load_configuration_schema_and_quote(): void
    {
        $this->seed(ProductTypeSeeder::class);

        $fashion = ProductType::query()->where('slug', 'fashion')->firstOrFail();
        $product = Product::factory()->create([
            'product_type_id' => $fashion->id,
            'price' => 25000,
            'slug' => 'fashion-tee',
            'is_active' => true,
        ]);

        $size = ProductAttribute::query()->where('slug', 'size')->firstOrFail();
        $color = ProductAttribute::query()->where('slug', 'color')->firstOrFail();
        $m = ProductAttributeValue::query()->where('product_attribute_id', $size->id)->where('slug', 'm')->firstOrFail();
        $black = ProductAttributeValue::query()->where('product_attribute_id', $color->id)->where('slug', 'black')->firstOrFail();

        $config = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'FASH-M-BLACK',
            'name' => 'M / Black',
            'price' => 27000,
            'is_active' => true,
        ]);
        $config->attributeValues()->sync([$m->id, $black->id]);

        Inventory::factory()->forVariant($config)->create([
            'quantity' => 8,
            'reserved_quantity' => 0,
        ]);

        $schema = $this->getJson("/api/v1/products/{$product->slug}/configuration");

        $schema->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_configurations', true)
            ->assertJsonPath('data.product_type.slug', 'fashion');

        $this->assertNotEmpty($schema->json('data.attributes'));
        $this->assertCount(1, $schema->json('data.configurations'));

        $quote = $this->postJson("/api/v1/products/{$product->slug}/quote", [
            'configuration_id' => $config->id,
            'quantity' => 2,
        ]);

        $quote->assertOk()
            ->assertJsonPath('data.configuration_id', $config->id)
            ->assertJsonPath('data.unit_price', '27000.00')
            ->assertJsonPath('data.line_total', '54000.00')
            ->assertJsonPath('data.breakdown.1.stage', 'configuration_override');
    }

    public function test_cart_requires_configuration_when_product_has_configurations(): void
    {
        $this->seed(ProductTypeSeeder::class);
        Sanctum::actingAs(User::factory()->create());

        $fashion = ProductType::query()->where('slug', 'fashion')->firstOrFail();
        $product = Product::factory()->create([
            'product_type_id' => $fashion->id,
            'price' => 20000,
            'is_active' => true,
        ]);

        $config = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'price' => 22000,
        ]);
        VariantPrice::query()->create([
            'product_variant_id' => $config->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 22000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $config->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 5,
            'reserved' => 0,
            'reorder_level' => 1,
            'safety_stock' => 0,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertStatus(422);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'configuration_id' => $config->id,
            'quantity' => 1,
        ])->assertCreated()
            ->assertJsonPath('success', true);
    }

    public function test_out_of_stock_configuration_cannot_be_added_to_cart(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $product = Product::factory()->create(['price' => 10000, 'is_active' => true]);
        $config = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'price' => 10000,
        ]);
        VariantPrice::query()->create([
            'product_variant_id' => $config->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 10000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $config->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 0,
            'reserved' => 0,
            'reorder_level' => 1,
            'safety_stock' => 0,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'configuration_id' => $config->id,
            'quantity' => 1,
        ])->assertStatus(422);
    }

    public function test_storefront_options_follow_dependency_engine_and_stock(): void
    {
        $this->seed(ProductTypeSeeder::class);

        $fashion = ProductType::query()->where('slug', 'fashion')->firstOrFail();
        $product = Product::factory()->create([
            'product_type_id' => $fashion->id,
            'price' => 25000,
            'slug' => 'fashion-deps',
            'is_active' => true,
        ]);

        $size = ProductAttribute::query()->where('slug', 'size')->firstOrFail();
        $color = ProductAttribute::query()->where('slug', 'color')->firstOrFail();
        $m = ProductAttributeValue::query()->where('product_attribute_id', $size->id)->where('slug', 'm')->firstOrFail();
        $xl = ProductAttributeValue::query()->where('product_attribute_id', $size->id)->where('slug', 'xl')->firstOrFail();
        $black = ProductAttributeValue::query()->where('product_attribute_id', $color->id)->where('slug', 'black')->firstOrFail();
        $red = ProductAttributeValue::query()->where('product_attribute_id', $color->id)->where('slug', 'red')->firstOrFail();

        $inStock = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'FASH-M-BLACK',
            'name' => 'M / Black',
            'is_active' => true,
        ]);
        $inStock->attributeValues()->sync([$m->id, $black->id]);
        Inventory::factory()->forVariant($inStock)->create([
            'quantity' => 4,
            'reserved_quantity' => 0,
        ]);

        $oos = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'FASH-M-RED',
            'name' => 'M / Red',
            'is_active' => true,
        ]);
        $oos->attributeValues()->sync([$m->id, $red->id]);
        Inventory::factory()->forVariant($oos)->create([
            'quantity' => 0,
            'reserved_quantity' => 0,
        ]);

        $xlBlack = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'FASH-XL-BLACK',
            'name' => 'XL / Black',
            'is_active' => true,
        ]);
        $xlBlack->attributeValues()->sync([$xl->id, $black->id]);
        Inventory::factory()->forVariant($xlBlack)->create([
            'quantity' => 2,
            'reserved_quantity' => 0,
        ]);

        // Out-of-stock-only color (red) is not offered as an available option.
        $base = $this->getJson("/api/v1/products/{$product->slug}/configuration");
        $base->assertOk();
        $allowedColors = $base->json("data.allowed_value_ids.{$color->id}");
        $this->assertContains($black->id, $allowedColors);
        $this->assertNotContains($red->id, $allowedColors);

        // Selecting red via query still cascades through dependency metadata + stock.
        $withRed = $this->getJson(
            "/api/v1/products/{$product->slug}/configuration?selections[{$color->id}]={$red->id}"
        );
        $withRed->assertOk();
        $allowedSizesForRed = $withRed->json("data.allowed_value_ids.{$size->id}");
        $this->assertSame([], $allowedSizesForRed);

        // Black allows sizes that exist in stock; XL+Red is never a sellable config here.
        $withBlack = $this->getJson(
            "/api/v1/products/{$product->slug}/configuration?selections[{$color->id}]={$black->id}"
        );
        $withBlack->assertOk();
        $allowedSizesForBlack = $withBlack->json("data.allowed_value_ids.{$size->id}");
        $this->assertContains($m->id, $allowedSizesForBlack);
        $this->assertContains($xl->id, $allowedSizesForBlack);
    }
}

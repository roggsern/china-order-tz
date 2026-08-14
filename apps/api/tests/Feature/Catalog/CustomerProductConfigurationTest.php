<?php

namespace Tests\Feature\Catalog;

use App\Enums\CatalogAttributeType;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Enums\VariantPriceType;
use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\ChinaCommercialStock;
use App\Models\Department;
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
        $product = Product::factory()->tzLocal()->create([
            'product_type_id' => $fashion->id,
            'price' => 0,
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
        ]);

        $config = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'price' => null,
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
        $product = Product::factory()->tzLocal()->create([
            'product_type_id' => $fashion->id,
            'price' => 0,
            'slug' => 'fashion-deps',
            'is_active' => true,
            'is_demo' => false,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
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
            'price' => null,
        ]);
        $inStock->attributeValues()->sync([$m->id, $black->id]);
        VariantPrice::query()->create([
            'product_variant_id' => $inStock->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 25000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $inStock->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 4,
            'reserved' => 0,
            'reorder_level' => 1,
            'safety_stock' => 0,
            'is_active' => true,
        ]);

        $oos = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'FASH-M-RED',
            'name' => 'M / Red',
            'is_active' => true,
            'price' => null,
        ]);
        $oos->attributeValues()->sync([$m->id, $red->id]);
        VariantPrice::query()->create([
            'product_variant_id' => $oos->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 25000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $oos->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 0,
            'reserved' => 0,
            'reorder_level' => 1,
            'safety_stock' => 0,
            'is_active' => true,
        ]);

        $xlBlack = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'FASH-XL-BLACK',
            'name' => 'XL / Black',
            'is_active' => true,
            'price' => null,
        ]);
        $xlBlack->attributeValues()->sync([$xl->id, $black->id]);
        VariantPrice::query()->create([
            'product_variant_id' => $xlBlack->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 27000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $xlBlack->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 2,
            'reserved' => 0,
            'reorder_level' => 1,
            'safety_stock' => 0,
            'is_active' => true,
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

    public function test_customer_catalog_variant_configuration_maps_attributes_and_variant_prices(): void
    {
        $department = Department::factory()->create();
        $category = Category::factory()->forDepartment($department)->create(['parent_id' => null]);
        $subcategory = Category::factory()->forDepartment($department)->create([
            'parent_id' => $category->id,
        ]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'name' => 'iPhone Style Phone',
            'slug' => 'iphone-style-phone',
            'is_active' => true,
        ]);

        $color = CatalogAttribute::factory()->create([
            'name' => 'Color',
            'slug' => 'color',
            'type' => CatalogAttributeType::Select,
        ]);
        $storage = CatalogAttribute::factory()->create([
            'name' => 'Storage',
            'slug' => 'storage',
            'type' => CatalogAttributeType::Select,
        ]);

        $black = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $color->id,
            'value' => 'Black',
            'slug' => 'black',
        ]);
        $white = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $color->id,
            'value' => 'White',
            'slug' => 'white',
        ]);
        $storage128 = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $storage->id,
            'value' => '128GB',
            'slug' => '128gb',
        ]);
        $storage256 = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $storage->id,
            'value' => '256GB',
            'slug' => '256gb',
        ]);

        $catalogType->attributes()->sync([
            $color->id => ['is_required' => true, 'sort_order' => 1],
            $storage->id => ['is_required' => true, 'sort_order' => 2],
        ]);

        $product = Product::factory()->chinaImport()->create([
            'category_id' => $subcategory->id,
            'catalog_product_type_id' => $catalogType->id,
            'slug' => 'iphone-15-pro-max',
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
            'visibility' => ProductVisibility::Public,
            'price' => 1500000,
        ]);

        $black128 = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'IPH15-BLK-128',
            'name' => 'OEM Black Metal 16GB 128GB',
            'price' => null,
            'is_active' => true,
        ]);
        $black128->catalogAttributeValues()->createMany([
            [
                'catalog_attribute_id' => $color->id,
                'option_id' => $black->id,
                'value_text' => $black->value,
            ],
            [
                'catalog_attribute_id' => $storage->id,
                'option_id' => $storage128->id,
                'value_text' => $storage128->value,
            ],
        ]);
        VariantPrice::query()->create([
            'product_variant_id' => $black128->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 1800000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $black128->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 5,
            'reserved' => 0,
            'reorder_level' => 1,
            'safety_stock' => 0,
            'is_active' => true,
        ]);
        ChinaCommercialStock::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $black128->id,
            'available_quantity' => 5,
            'reserved_quantity' => 0,
            'ordered_quantity' => 0,
        ]);

        $white256 = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'IPH15-WHT-256',
            'name' => 'OEM White Metal 16GB 256GB',
            'price' => null,
            'is_active' => true,
        ]);
        $white256->catalogAttributeValues()->createMany([
            [
                'catalog_attribute_id' => $color->id,
                'option_id' => $white->id,
                'value_text' => $white->value,
            ],
            [
                'catalog_attribute_id' => $storage->id,
                'option_id' => $storage256->id,
                'value_text' => $storage256->value,
            ],
        ]);
        VariantPrice::query()->create([
            'product_variant_id' => $white256->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 2000000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $white256->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 3,
            'reserved' => 0,
            'reorder_level' => 1,
            'safety_stock' => 0,
            'is_active' => true,
        ]);
        ChinaCommercialStock::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $white256->id,
            'available_quantity' => 3,
            'reserved_quantity' => 0,
            'ordered_quantity' => 0,
        ]);

        $schema = $this->getJson("/api/v1/products/{$product->slug}/configuration");

        $schema->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_configurations', true)
            ->assertJsonPath('data.product_type.slug', 'iphone-style-phone');

        $attributeIds = collect($schema->json('data.attributes'))->pluck('id', 'slug');
        $this->assertTrue($attributeIds->has('color'));
        $this->assertTrue($attributeIds->has('storage'));

        $configurations = $schema->json('data.configurations');
        $this->assertCount(2, $configurations);

        $black128Row = collect($configurations)->firstWhere('id', $black128->id);
        $this->assertNotNull($black128Row);
        $this->assertSame('1800000.00', (string) $black128Row['price']);
        $this->assertNotEmpty($black128Row['attribute_value_ids']);
        $this->assertCount(2, $black128Row['attribute_values']);

        $attributeValuesByName = collect($black128Row['attribute_values'])
            ->keyBy('attribute_name');
        $this->assertSame('Black', $attributeValuesByName->get('Color')['value']);
        $this->assertSame('128GB', $attributeValuesByName->get('Storage')['value']);
        $this->assertSame([
            ['attribute' => 'Color', 'value' => 'Black'],
            ['attribute' => 'Storage', 'value' => '128GB'],
        ], $black128Row['display_attributes']);

        $allowedColors = $schema->json("data.allowed_value_ids.{$color->id}");
        $this->assertContains($black->id, $allowedColors);
        $this->assertContains($white->id, $allowedColors);

        $matched = $this->getJson(
            "/api/v1/products/{$product->slug}/configuration?selections[{$color->id}]={$black->id}&selections[{$storage->id}]={$storage128->id}",
        );
        $matched->assertOk()
            ->assertJsonPath('data.matched_configuration_id', $black128->id)
            ->assertJsonPath('data.is_complete', true)
            ->assertJsonPath('data.is_in_stock', true);

        $quote = $this->postJson("/api/v1/products/{$product->slug}/quote", [
            'configuration_id' => $black128->id,
            'quantity' => 1,
        ]);

        $quote->assertOk()
            ->assertJsonPath('data.configuration_id', $black128->id)
            ->assertJsonPath('data.unit_price', '1800000.00')
            ->assertJsonPath('data.line_total', '1800000.00');
    }

    public function test_catalog_variant_matching_ignores_non_configuration_attributes(): void
    {
        $department = Department::factory()->create();
        $category = Category::factory()->forDepartment($department)->create(['parent_id' => null]);
        $subcategory = Category::factory()->forDepartment($department)->create([
            'parent_id' => $category->id,
        ]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'is_active' => true,
        ]);

        $brand = CatalogAttribute::factory()->create([
            'name' => 'Brand',
            'slug' => 'brand',
            'type' => CatalogAttributeType::Select,
        ]);
        $color = CatalogAttribute::factory()->create([
            'name' => 'Color',
            'slug' => 'color',
            'type' => CatalogAttributeType::Select,
        ]);
        $material = CatalogAttribute::factory()->create([
            'name' => 'Material',
            'slug' => 'material',
            'type' => CatalogAttributeType::Select,
        ]);
        $ram = CatalogAttribute::factory()->create([
            'name' => 'RAM',
            'slug' => 'ram',
            'type' => CatalogAttributeType::Select,
        ]);
        $storage = CatalogAttribute::factory()->create([
            'name' => 'Storage',
            'slug' => 'storage',
            'type' => CatalogAttributeType::Select,
        ]);

        $oem = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $brand->id,
            'value' => 'OEM',
            'slug' => 'oem',
        ]);
        $black = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $color->id,
            'value' => 'Black',
            'slug' => 'black',
        ]);
        $white = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $color->id,
            'value' => 'White',
            'slug' => 'white',
        ]);
        $metal = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $material->id,
            'value' => 'Metal',
            'slug' => 'metal',
        ]);
        $ram16 = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $ram->id,
            'value' => '16GB',
            'slug' => '16gb',
        ]);
        $storage128 = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $storage->id,
            'value' => '128GB',
            'slug' => '128gb',
        ]);
        $storage256 = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $storage->id,
            'value' => '256GB',
            'slug' => '256gb',
        ]);

        $catalogType->attributes()->sync([
            $brand->id => ['is_required' => false, 'sort_order' => 1],
            $color->id => ['is_required' => true, 'sort_order' => 2],
            $material->id => ['is_required' => false, 'sort_order' => 3],
            $ram->id => ['is_required' => false, 'sort_order' => 4],
            $storage->id => ['is_required' => true, 'sort_order' => 5],
        ]);

        $product = Product::factory()->chinaImport()->create([
            'category_id' => $subcategory->id,
            'catalog_product_type_id' => $catalogType->id,
            'slug' => 'oem-metal-phone',
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
            'visibility' => ProductVisibility::Public,
            'price' => 1500000,
        ]);

        $black256 = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'OEM-BLK-256',
            'name' => 'OEM Black Metal 16GB 256GB',
            'price' => null,
            'is_active' => true,
        ]);
        $black256->catalogAttributeValues()->createMany([
            ['catalog_attribute_id' => $brand->id, 'option_id' => $oem->id, 'value_text' => $oem->value],
            ['catalog_attribute_id' => $color->id, 'option_id' => $black->id, 'value_text' => $black->value],
            ['catalog_attribute_id' => $material->id, 'option_id' => $metal->id, 'value_text' => $metal->value],
            ['catalog_attribute_id' => $ram->id, 'option_id' => $ram16->id, 'value_text' => $ram16->value],
            ['catalog_attribute_id' => $storage->id, 'option_id' => $storage256->id, 'value_text' => $storage256->value],
        ]);
        VariantPrice::query()->create([
            'product_variant_id' => $black256->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 2000000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $black256->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 4,
            'reserved' => 0,
            'reorder_level' => 1,
            'safety_stock' => 0,
            'is_active' => true,
        ]);
        ChinaCommercialStock::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $black256->id,
            'available_quantity' => 4,
            'reserved_quantity' => 0,
            'ordered_quantity' => 0,
        ]);

        $white128 = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'OEM-WHT-128',
            'name' => 'OEM White Metal 16GB 128GB',
            'price' => null,
            'is_active' => true,
        ]);
        $white128->catalogAttributeValues()->createMany([
            ['catalog_attribute_id' => $brand->id, 'option_id' => $oem->id, 'value_text' => $oem->value],
            ['catalog_attribute_id' => $color->id, 'option_id' => $white->id, 'value_text' => $white->value],
            ['catalog_attribute_id' => $material->id, 'option_id' => $metal->id, 'value_text' => $metal->value],
            ['catalog_attribute_id' => $ram->id, 'option_id' => $ram16->id, 'value_text' => $ram16->value],
            ['catalog_attribute_id' => $storage->id, 'option_id' => $storage128->id, 'value_text' => $storage128->value],
        ]);
        VariantPrice::query()->create([
            'product_variant_id' => $white128->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 1800000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $white128->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 2,
            'reserved' => 0,
            'reorder_level' => 1,
            'safety_stock' => 0,
            'is_active' => true,
        ]);
        ChinaCommercialStock::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $white128->id,
            'available_quantity' => 2,
            'reserved_quantity' => 0,
            'ordered_quantity' => 0,
        ]);

        $schema = $this->getJson("/api/v1/products/{$product->slug}/configuration");
        $schema->assertOk();

        $attributes = collect($schema->json('data.attributes'))->keyBy('slug');
        $this->assertFalse($attributes->get('brand')['participates_in_configuration']);
        $this->assertTrue($attributes->get('color')['participates_in_configuration']);
        $this->assertFalse($attributes->get('material')['participates_in_configuration']);
        $this->assertFalse($attributes->get('ram')['participates_in_configuration']);
        $this->assertTrue($attributes->get('storage')['participates_in_configuration']);

        $matched = $this->getJson(
            "/api/v1/products/{$product->slug}/configuration?selections[{$color->id}]={$black->id}&selections[{$storage->id}]={$storage256->id}",
        );

        $matched->assertOk()
            ->assertJsonPath('data.matched_configuration_id', $black256->id)
            ->assertJsonPath('data.is_complete', true)
            ->assertJsonPath('data.is_in_stock', true);

        $matchedConfiguration = collect($matched->json('data.configurations'))
            ->firstWhere('id', $black256->id);
        $this->assertSame('2000000.00', (string) $matchedConfiguration['price']);

        $quote = $this->postJson("/api/v1/products/{$product->slug}/quote", [
            'configuration_id' => $black256->id,
            'quantity' => 1,
        ]);

        $quote->assertOk()
            ->assertJsonPath('data.configuration_id', $black256->id)
            ->assertJsonPath('data.unit_price', '2000000.00');
    }

    public function test_peer_color_and_size_options_remain_selectable_after_partial_selection(): void
    {
        $this->seed(ProductTypeSeeder::class);

        $fashion = ProductType::query()->where('slug', 'fashion')->firstOrFail();
        $product = Product::factory()->tzLocal()->create([
            'product_type_id' => $fashion->id,
            'price' => 0,
            'slug' => 'fashion-peer-options',
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'is_demo' => false,
        ]);

        $size = ProductAttribute::query()->where('slug', 'size')->firstOrFail();
        $color = ProductAttribute::query()->where('slug', 'color')->firstOrFail();
        $m = ProductAttributeValue::query()->where('product_attribute_id', $size->id)->where('slug', 'm')->firstOrFail();
        $xl = ProductAttributeValue::query()->where('product_attribute_id', $size->id)->where('slug', 'xl')->firstOrFail();
        $black = ProductAttributeValue::query()->where('product_attribute_id', $color->id)->where('slug', 'black')->firstOrFail();
        $red = ProductAttributeValue::query()->where('product_attribute_id', $color->id)->where('slug', 'red')->firstOrFail();

        $mBlack = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'PEER-M-BLACK',
            'name' => 'M / Black',
            'is_active' => true,
            'price' => null,
        ]);
        $mBlack->attributeValues()->sync([$m->id, $black->id]);
        VariantPrice::query()->create([
            'product_variant_id' => $mBlack->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 25000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $mBlack->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 4,
            'reserved' => 0,
            'reorder_level' => 1,
            'safety_stock' => 0,
            'is_active' => true,
        ]);

        $mRed = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'PEER-M-RED',
            'name' => 'M / Red',
            'is_active' => true,
            'price' => null,
        ]);
        $mRed->attributeValues()->sync([$m->id, $red->id]);
        VariantPrice::query()->create([
            'product_variant_id' => $mRed->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 25000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $mRed->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 3,
            'reserved' => 0,
            'reorder_level' => 1,
            'safety_stock' => 0,
            'is_active' => true,
        ]);

        $xlBlack = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'PEER-XL-BLACK',
            'name' => 'XL / Black',
            'is_active' => true,
            'price' => null,
        ]);
        $xlBlack->attributeValues()->sync([$xl->id, $black->id]);
        VariantPrice::query()->create([
            'product_variant_id' => $xlBlack->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 27000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $xlBlack->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 2,
            'reserved' => 0,
            'reorder_level' => 1,
            'safety_stock' => 0,
            'is_active' => true,
        ]);

        // XL / Red intentionally missing — incompatible when Size=XL and Color=Red.

        // A. Color selected → peer colors remain selectable where eligible.
        $withBlack = $this->getJson(
            "/api/v1/products/{$product->slug}/configuration?selections[{$color->id}]={$black->id}"
        );
        $withBlack->assertOk();
        $allowedColorsWithBlack = $withBlack->json("data.allowed_value_ids.{$color->id}");
        $this->assertContains($black->id, $allowedColorsWithBlack);
        $this->assertContains($red->id, $allowedColorsWithBlack);
        $this->assertNull($withBlack->json('data.matched_configuration_id'));
        $this->assertFalse((bool) $withBlack->json('data.is_complete'));

        // B. Size selected → peer sizes remain selectable where eligible.
        $withM = $this->getJson(
            "/api/v1/products/{$product->slug}/configuration?selections[{$size->id}]={$m->id}"
        );
        $withM->assertOk();
        $allowedSizesWithM = $withM->json("data.allowed_value_ids.{$size->id}");
        $this->assertContains($m->id, $allowedSizesWithM);
        $this->assertContains($xl->id, $allowedSizesWithM);

        // Color → Size cascade still applies for the selected color.
        $allowedSizesForBlack = $withBlack->json("data.allowed_value_ids.{$size->id}");
        $this->assertContains($m->id, $allowedSizesForBlack);
        $this->assertContains($xl->id, $allowedSizesForBlack);

        // Size → Color compatibility: XL only exists with Black.
        $withXl = $this->getJson(
            "/api/v1/products/{$product->slug}/configuration?selections[{$size->id}]={$xl->id}"
        );
        $withXl->assertOk();
        $allowedColorsForXl = $withXl->json("data.allowed_value_ids.{$color->id}");
        $this->assertContains($black->id, $allowedColorsForXl);
        $this->assertNotContains($red->id, $allowedColorsForXl);

        // Exact match still requires the full selection.
        $exact = $this->getJson(
            "/api/v1/products/{$product->slug}/configuration?selections[{$color->id}]={$black->id}&selections[{$size->id}]={$m->id}"
        );
        $exact->assertOk()
            ->assertJsonPath('data.matched_configuration_id', $mBlack->id)
            ->assertJsonPath('data.is_complete', true)
            ->assertJsonPath('data.is_in_stock', true);
    }

    public function test_peer_option_allowlists_still_exclude_out_of_stock_combinations(): void
    {
        $this->seed(ProductTypeSeeder::class);

        $fashion = ProductType::query()->where('slug', 'fashion')->firstOrFail();
        $product = Product::factory()->tzLocal()->create([
            'product_type_id' => $fashion->id,
            'price' => 0,
            'slug' => 'fashion-peer-oos',
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'is_demo' => false,
        ]);

        $size = ProductAttribute::query()->where('slug', 'size')->firstOrFail();
        $color = ProductAttribute::query()->where('slug', 'color')->firstOrFail();
        $m = ProductAttributeValue::query()->where('product_attribute_id', $size->id)->where('slug', 'm')->firstOrFail();
        $black = ProductAttributeValue::query()->where('product_attribute_id', $color->id)->where('slug', 'black')->firstOrFail();
        $red = ProductAttributeValue::query()->where('product_attribute_id', $color->id)->where('slug', 'red')->firstOrFail();

        $mBlack = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'PEER-OOS-M-BLACK',
            'name' => 'M / Black',
            'is_active' => true,
            'price' => null,
        ]);
        $mBlack->attributeValues()->sync([$m->id, $black->id]);
        VariantPrice::query()->create([
            'product_variant_id' => $mBlack->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 25000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $mBlack->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 4,
            'reserved' => 0,
            'reorder_level' => 1,
            'safety_stock' => 0,
            'is_active' => true,
        ]);

        $mRed = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'PEER-OOS-M-RED',
            'name' => 'M / Red',
            'is_active' => true,
            'price' => null,
        ]);
        $mRed->attributeValues()->sync([$m->id, $red->id]);
        VariantPrice::query()->create([
            'product_variant_id' => $mRed->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 25000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $mRed->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 0,
            'reserved' => 0,
            'reorder_level' => 1,
            'safety_stock' => 0,
            'is_active' => true,
        ]);

        $withBlack = $this->getJson(
            "/api/v1/products/{$product->slug}/configuration?selections[{$color->id}]={$black->id}"
        );
        $withBlack->assertOk();
        $allowedColors = $withBlack->json("data.allowed_value_ids.{$color->id}");
        $this->assertContains($black->id, $allowedColors);
        $this->assertNotContains($red->id, $allowedColors);
    }
}

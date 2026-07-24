<?php

namespace Tests\Unit\Services\ProductConfiguration;

use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductType;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttributeValue;
use App\Services\ProductConfiguration\LegacyConfigurationProductDetector;
use Database\Seeders\ProductTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyConfigurationProductDetectorTest extends TestCase
{
    use RefreshDatabase;

    private LegacyConfigurationProductDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->detector = app(LegacyConfigurationProductDetector::class);
    }

    public function test_is_legacy_configuration_product_when_variant_has_legacy_attribute_values(): void
    {
        $this->seed(ProductTypeSeeder::class);

        $phones = ProductType::query()->where('slug', 'phones')->firstOrFail();
        $category = Category::factory()->create(['product_type_id' => $phones->id]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $category->id,
            'is_active' => true,
        ]);

        $storage = ProductAttribute::query()->where('slug', 'storage')->firstOrFail();
        $storage128 = ProductAttributeValue::query()
            ->where('product_attribute_id', $storage->id)
            ->where('slug', '128gb')
            ->firstOrFail();

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'catalog_product_type_id' => $catalogType->id,
            'product_type_id' => $phones->id,
        ]);

        $variant = ProductVariant::factory()->for($product)->create();
        $variant->attributeValues()->sync([$storage128->id]);

        $loaded = $product->fresh()->load('variants.attributeValues');

        $this->assertTrue($this->detector->isLegacyConfigurationProduct($loaded));
    }

    public function test_is_not_legacy_configuration_product_for_simple_catalog_product(): void
    {
        $category = Category::factory()->create();
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $category->id,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'catalog_product_type_id' => $catalogType->id,
            'product_type_id' => null,
        ]);

        $this->assertFalse($this->detector->isLegacyConfigurationProduct($product));
    }

    public function test_is_not_legacy_configuration_product_for_canonical_variant_rows(): void
    {
        $category = Category::factory()->create();
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $category->id,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'catalog_product_type_id' => $catalogType->id,
        ]);

        $variant = ProductVariant::factory()->for($product)->create();
        ProductVariantAttributeValue::factory()->create([
            'product_variant_id' => $variant->id,
        ]);

        $loaded = $product->fresh()->load('variants.catalogAttributeValues');

        $this->assertFalse($this->detector->isLegacyConfigurationProduct($loaded));
    }
}

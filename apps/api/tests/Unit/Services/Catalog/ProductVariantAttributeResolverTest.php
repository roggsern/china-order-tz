<?php

namespace Tests\Unit\Services\Catalog;

use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Services\Catalog\ProductVariantAttributeResolver;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantAttributeResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_catalog_attributes(): void
    {
        ['variant' => $variant] = CatalogCartFixture::purchasable(50000);

        $color = CatalogAttribute::factory()->create(['name' => 'Color', 'slug' => 'color-attr-res']);
        $black = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $color->id,
            'value' => 'Black',
            'slug' => 'black-attr-res',
        ]);
        $variant->catalogAttributeValues()->create([
            'catalog_attribute_id' => $color->id,
            'option_id' => $black->id,
            'value_text' => 'Black',
        ]);

        $rows = app(ProductVariantAttributeResolver::class)->resolve($variant->fresh());

        $this->assertSame([
            ['attribute' => 'Color', 'value' => 'Black'],
        ], $rows);
    }

    public function test_falls_back_to_legacy_attribute_values(): void
    {
        ['variant' => $variant] = CatalogCartFixture::purchasable(50000);

        $legacyAttribute = ProductAttribute::factory()->create(['name' => 'Storage']);
        $legacyValue = ProductAttributeValue::factory()->create([
            'product_attribute_id' => $legacyAttribute->id,
            'value' => '128GB',
        ]);
        $variant->attributeValues()->sync([$legacyValue->id]);

        $rows = app(ProductVariantAttributeResolver::class)->resolve(
            ProductVariant::query()->with(['attributeValues.attribute', 'catalogAttributeValues'])->findOrFail($variant->id),
        );

        $this->assertSame([
            ['attribute' => 'Storage', 'value' => '128GB'],
        ], $rows);
    }

    public function test_catalog_attributes_win_when_both_exist(): void
    {
        ['variant' => $variant] = CatalogCartFixture::purchasable(50000);

        $color = CatalogAttribute::factory()->create(['name' => 'Color', 'slug' => 'color-priority']);
        $black = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $color->id,
            'value' => 'Black',
            'slug' => 'black-priority',
        ]);
        $variant->catalogAttributeValues()->create([
            'catalog_attribute_id' => $color->id,
            'option_id' => $black->id,
            'value_text' => 'Black',
        ]);

        $legacyAttribute = ProductAttribute::factory()->create(['name' => 'Color']);
        $legacyValue = ProductAttributeValue::factory()->create([
            'product_attribute_id' => $legacyAttribute->id,
            'value' => 'Red',
        ]);
        $variant->attributeValues()->sync([$legacyValue->id]);

        $rows = app(ProductVariantAttributeResolver::class)->resolve($variant->fresh());

        $this->assertSame([
            ['attribute' => 'Color', 'value' => 'Black'],
        ], $rows);
    }
}

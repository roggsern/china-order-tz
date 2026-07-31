<?php

namespace Tests\Feature\Catalog;

use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerProductVariantAttributeResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_detail_prefers_catalog_display_attributes(): void
    {
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(75000);
        $product->update(['slug' => 'attr-priority-phone', 'name' => 'Attr Priority Phone']);

        $color = CatalogAttribute::factory()->create(['name' => 'Color', 'slug' => 'color-pdp']);
        $black = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $color->id,
            'value' => 'Black',
            'slug' => 'black-pdp',
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

        $response = $this->getJson('/api/v1/products/attr-priority-phone')->assertOk();

        $variantPayload = collect($response->json('data.variants'))
            ->firstWhere('id', $variant->id);

        $this->assertNotNull($variantPayload);
        $this->assertSame([
            ['attribute' => 'Color', 'value' => 'Black'],
        ], $variantPayload['display_attributes']);
        $this->assertSame('Black', $variantPayload['attribute_values'][0]['value'] ?? null);
        $this->assertSame('Color', $variantPayload['attribute_values'][0]['attribute']['name'] ?? null);
    }
}

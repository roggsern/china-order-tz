<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductType;
use App\Models\ProductVariant;
use Database\Seeders\ProductTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductConfigurationGridTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_configurations_respects_dependency_rules(): void
    {
        $this->seed(ProductTypeSeeder::class);
        Sanctum::actingAs(Admin::factory()->create());

        $fashion = ProductType::query()->where('slug', 'fashion')->firstOrFail();
        $size = ProductAttribute::query()->where('slug', 'size')->firstOrFail();
        $color = ProductAttribute::query()->where('slug', 'color')->firstOrFail();

        $xl = ProductAttributeValue::query()
            ->where('product_attribute_id', $size->id)
            ->where('slug', 'xl')
            ->firstOrFail();
        $m = ProductAttributeValue::query()
            ->where('product_attribute_id', $size->id)
            ->where('slug', 'm')
            ->firstOrFail();
        $black = ProductAttributeValue::query()
            ->where('product_attribute_id', $color->id)
            ->where('slug', 'black')
            ->firstOrFail();
        $red = ProductAttributeValue::query()
            ->where('product_attribute_id', $color->id)
            ->where('slug', 'red')
            ->firstOrFail();

        $response = $this->postJson(
            "/api/v1/admin/product-types/{$fashion->id}/generate-configurations",
            [
                'base_sku' => 'DEMO',
                'default_price' => 25000,
                'selected_values' => [
                    $size->id => [$xl->id, $m->id],
                    $color->id => [$black->id, $red->id],
                ],
            ],
        );

        $response->assertOk()->assertJsonPath('success', true);

        $configs = collect($response->json('data.configurations'));

        // XL+Red blocked by Fashion dependency metadata; XL+Black and M+* allowed.
        $this->assertCount(3, $configs);

        $pairs = $configs->map(function (array $row) use ($size, $color) {
            $byAttr = collect($row['selections']);

            return ($byAttr[$size->id] ?? '').'|'.($byAttr[$color->id] ?? '');
        })->all();

        $this->assertContains($xl->id.'|'.$black->id, $pairs);
        $this->assertContains($m->id.'|'.$black->id, $pairs);
        $this->assertContains($m->id.'|'.$red->id, $pairs);
        $this->assertNotContains($xl->id.'|'.$red->id, $pairs);

        $this->assertNotEmpty($configs->first()['sku']);
    }

    public function test_admin_can_create_product_with_configurations(): void
    {
        $this->seed(ProductTypeSeeder::class);
        Sanctum::actingAs(Admin::factory()->create());

        $phones = ProductType::query()->where('slug', 'phones')->firstOrFail();
        $category = Category::factory()->create(['product_type_id' => $phones->id]);

        $storage = ProductAttribute::query()->where('slug', 'storage')->firstOrFail();
        $color = ProductAttribute::query()->where('slug', 'color')->firstOrFail();
        $condition = ProductAttribute::query()->where('slug', 'condition')->firstOrFail();

        $storage128 = ProductAttributeValue::query()
            ->where('product_attribute_id', $storage->id)
            ->where('slug', '128gb')
            ->firstOrFail();
        $black = ProductAttributeValue::query()
            ->where('product_attribute_id', $color->id)
            ->where('slug', 'black')
            ->firstOrFail();
        $conditionNew = ProductAttributeValue::query()
            ->where('product_attribute_id', $condition->id)
            ->where('slug', 'new')
            ->firstOrFail();

        $payload = [
            'name' => 'Demo Phone',
            'category_id' => $category->id,
            'sku' => 'PHONE-BASE-1',
            'price' => 500000,
            'stock_quantity' => 0,
            'status' => true,
            'configurations' => [
                [
                    'attribute_value_ids' => [$storage128->id, $black->id, $conditionNew->id],
                    'sku' => 'PHONE-BASE-1-128-BLACK-NEW',
                    'stock_quantity' => 12,
                    'price' => 520000,
                    'barcode' => null,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/admin/products', $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sku', 'PHONE-BASE-1');

        $product = Product::query()->where('sku', 'PHONE-BASE-1')->firstOrFail();
        $this->assertSame($phones->id, $product->product_type_id);

        $variant = ProductVariant::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame('PHONE-BASE-1-128-BLACK-NEW', $variant->sku);
        $this->assertSame('520000.00', (string) $variant->price);
        $this->assertCount(3, $variant->attributeValues);
        $this->assertSame(12, (int) $variant->inventory?->quantity);
    }

    public function test_rejected_invalid_configuration_combination(): void
    {
        $this->seed(ProductTypeSeeder::class);
        Sanctum::actingAs(Admin::factory()->create());

        $fashion = ProductType::query()->where('slug', 'fashion')->firstOrFail();
        $category = Category::factory()->create(['product_type_id' => $fashion->id]);

        $size = ProductAttribute::query()->where('slug', 'size')->firstOrFail();
        $color = ProductAttribute::query()->where('slug', 'color')->firstOrFail();

        $xl = ProductAttributeValue::query()
            ->where('product_attribute_id', $size->id)
            ->where('slug', 'xl')
            ->firstOrFail();
        $red = ProductAttributeValue::query()
            ->where('product_attribute_id', $color->id)
            ->where('slug', 'red')
            ->firstOrFail();

        $this->postJson('/api/v1/admin/products', [
            'name' => 'Bad Fashion Combo',
            'category_id' => $category->id,
            'sku' => 'FASH-BAD-1',
            'price' => 20000,
            'status' => true,
            'configurations' => [
                [
                    'attribute_value_ids' => [$xl->id, $red->id],
                    'stock_quantity' => 1,
                ],
            ],
        ])->assertStatus(422);
    }
}

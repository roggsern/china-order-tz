<?php

namespace Tests\Feature\Admin;

use App\Enums\CatalogAttributeType;
use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Enums\VariantPriceType;
use App\Models\Admin;
use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Department;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductShippingOption;
use App\Models\ProductType;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use Database\Seeders\ProductTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductActiveDegradationGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(Admin::factory()->create());
    }

    public function test_active_china_simple_product_cannot_set_price_to_zero(): void
    {
        $product = $this->makePublishableProduct(CommerceChannelCode::ChinaImport);
        ProductShippingOption::factory()->air(8000)->create(['product_id' => $product->id]);

        $originalPrice = (string) $product->price;

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'price' => 0,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price']);

        $this->assertSame($originalPrice, (string) Product::query()->whereKey($product->id)->value('price'));
    }

    public function test_active_tz_product_cannot_remove_store(): void
    {
        $store = $this->makeTzStore();
        $product = $this->makePublishableProduct(CommerceChannelCode::TzLocal, [
            'store_id' => $store->id,
        ]);

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'store_id' => null,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['store_id']);

        $this->assertSame($store->id, Product::query()->whereKey($product->id)->value('store_id'));
    }

    public function test_active_variant_product_cannot_replace_existing_sellable_variants_with_unsellable_ones(): void
    {
        ['product' => $product, 'color' => $color, 'black' => $black, 'white' => $white] = $this->makeCatalogVariantProduct();

        ProductShippingOption::factory()->air(8000)->create(['product_id' => $product->id]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Black',
            'sku' => 'VAR-GUARD-BLK',
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 1,
        ]);
        $variant->catalogAttributeValues()->create([
            'catalog_attribute_id' => $color->id,
            'option_id' => $black->id,
            'value_text' => $black->value,
        ]);

        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 25000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 10,
            'reserved' => 0,
            'reorder_level' => 2,
            'safety_stock' => 0,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/admin/products/'.$product->id.'/variants/generate', [
            'replace_existing' => true,
            'attributes' => [
                [
                    'catalog_attribute_id' => $color->id,
                    'option_ids' => [$white->id],
                ],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['variants']);

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'deleted_at' => null,
        ]);
    }

    public function test_active_configuration_product_cannot_sync_away_all_sellable_variants(): void
    {
        $this->seed(ProductTypeSeeder::class);

        $phones = ProductType::query()->where('slug', 'phones')->firstOrFail();
        $category = Category::factory()->create(['product_type_id' => $phones->id]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $category->id,
            'is_active' => true,
        ]);

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

        $create = $this->postJson('/api/v1/admin/products', [
            'name' => 'Active Config Guard Phone',
            'category_id' => $category->id,
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => CommerceChannel::query()->where('code', 'CHINA_IMPORT')->value('id'),
            'sku' => 'CFG-GUARD-1',
            'price' => 500000,
            'air_shipping_price' => 8000,
            'stock_quantity' => 0,
            'status' => 'draft',
            'configurations' => [
                [
                    'attribute_value_ids' => [$storage128->id, $black->id, $conditionNew->id],
                    'sku' => 'CFG-GUARD-1-128-BLACK-NEW',
                    'stock_quantity' => 8,
                    'price' => 520000,
                ],
            ],
        ]);

        $create->assertCreated();
        $productId = $create->json('data.id');

        $this->putJson('/api/v1/admin/products/'.$productId, [
            'status' => 'active',
            'visibility' => 'public',
        ])->assertOk();
        $variantId = ProductVariant::query()->where('product_id', $productId)->value('id');
        $this->assertNotNull($variantId);

        $this->putJson('/api/v1/admin/products/'.$productId, [
            'configurations' => [],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['variants']);

        $this->assertDatabaseHas('product_variants', [
            'id' => $variantId,
            'deleted_at' => null,
        ]);
    }

    public function test_draft_products_allow_degrading_mutations(): void
    {
        $chinaDraft = $this->makePublishableProduct(CommerceChannelCode::ChinaImport, [
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'is_active' => false,
        ]);
        ProductShippingOption::factory()->air(8000)->create(['product_id' => $chinaDraft->id]);

        $this->putJson('/api/v1/admin/products/'.$chinaDraft->id, [
            'price' => 0,
        ])->assertOk();

        $this->assertSame('0.00', (string) Product::query()->whereKey($chinaDraft->id)->value('price'));

        $store = $this->makeTzStore();
        $tzDraft = $this->makePublishableProduct(CommerceChannelCode::TzLocal, [
            'store_id' => $store->id,
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'is_active' => false,
        ]);

        $this->putJson('/api/v1/admin/products/'.$tzDraft->id, [
            'store_id' => null,
        ])->assertOk();

        $this->assertNull(Product::query()->whereKey($tzDraft->id)->value('store_id'));

        ['product' => $variantDraft, 'color' => $color, 'black' => $black, 'white' => $white] = $this->makeCatalogVariantProduct([
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'is_active' => false,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $variantDraft->id,
            'name' => 'Black',
            'sku' => 'DRAFT-VAR-BLK',
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 1,
        ]);
        $variant->catalogAttributeValues()->create([
            'catalog_attribute_id' => $color->id,
            'option_id' => $black->id,
            'value_text' => $black->value,
        ]);

        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 18000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 5,
            'reserved' => 0,
            'reorder_level' => 1,
            'safety_stock' => 0,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/admin/products/'.$variantDraft->id.'/variants/generate', [
            'replace_existing' => true,
            'attributes' => [
                [
                    'catalog_attribute_id' => $color->id,
                    'option_ids' => [$white->id],
                ],
            ],
        ])->assertOk();

        $this->assertSoftDeleted('product_variants', ['id' => $variant->id]);

        $this->seed(ProductTypeSeeder::class);
        $phones = ProductType::query()->where('slug', 'phones')->firstOrFail();
        $category = Category::factory()->create(['product_type_id' => $phones->id]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $category->id,
            'is_active' => true,
        ]);
        $storage = ProductAttribute::query()->where('slug', 'storage')->firstOrFail();
        $colorAttr = ProductAttribute::query()->where('slug', 'color')->firstOrFail();
        $condition = ProductAttribute::query()->where('slug', 'condition')->firstOrFail();
        $storage128 = ProductAttributeValue::query()
            ->where('product_attribute_id', $storage->id)
            ->where('slug', '128gb')
            ->firstOrFail();
        $blackValue = ProductAttributeValue::query()
            ->where('product_attribute_id', $colorAttr->id)
            ->where('slug', 'black')
            ->firstOrFail();
        $conditionNew = ProductAttributeValue::query()
            ->where('product_attribute_id', $condition->id)
            ->where('slug', 'new')
            ->firstOrFail();

        $configDraft = $this->postJson('/api/v1/admin/products', [
            'name' => 'Draft Config Guard Phone',
            'category_id' => $category->id,
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => CommerceChannel::query()->where('code', 'CHINA_IMPORT')->value('id'),
            'sku' => 'CFG-DRAFT-1',
            'price' => 500000,
            'status' => 'draft',
            'configurations' => [
                [
                    'attribute_value_ids' => [$storage128->id, $blackValue->id, $conditionNew->id],
                    'sku' => 'CFG-DRAFT-1-128-BLACK-NEW',
                    'stock_quantity' => 3,
                    'price' => 510000,
                ],
            ],
        ]);

        $configDraft->assertCreated();
        $configProductId = $configDraft->json('data.id');

        $this->putJson('/api/v1/admin/products/'.$configProductId, [
            'configurations' => [],
        ])->assertOk();

        $this->assertSame(0, ProductVariant::query()->where('product_id', $configProductId)->count());
    }

    /**
     * @param  array<string, mixed>  $productOverrides
     * @return array{
     *     product: Product,
     *     color: CatalogAttribute,
     *     black: CatalogAttributeOption,
     *     white: CatalogAttributeOption
     * }
     */
    private function makeCatalogVariantProduct(array $productOverrides = []): array
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

        $color = CatalogAttribute::factory()->create([
            'type' => CatalogAttributeType::Select,
        ]);
        $black = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $color->id,
            'value' => 'Black',
        ]);
        $white = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $color->id,
            'value' => 'White',
        ]);

        $catalogType->attributes()->sync([
            $color->id => ['is_required' => false, 'sort_order' => 1],
        ]);

        $product = Product::factory()->chinaImport()->create(array_merge([
            'category_id' => $subcategory->id,
            'catalog_product_type_id' => $catalogType->id,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
            'visibility' => ProductVisibility::Public,
            'price' => 0,
        ], $productOverrides));

        return [
            'product' => $product,
            'color' => $color,
            'black' => $black,
            'white' => $white,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePublishableProduct(
        CommerceChannelCode $channelCode,
        array $overrides = [],
    ): Product {
        $department = Department::factory()->create();
        $category = Category::factory()->forDepartment($department)->create(['parent_id' => null]);
        $subcategory = Category::factory()->forDepartment($department)->create([
            'parent_id' => $category->id,
        ]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'is_active' => true,
        ]);

        $channelId = CommerceChannel::query()->where('code', $channelCode->value)->value('id')
            ?? match ($channelCode) {
                CommerceChannelCode::TzLocal => CommerceChannel::factory()->tanzania()->create()->id,
                default => CommerceChannel::factory()->china()->create()->id,
            };

        $defaults = [
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_demo' => false,
            'visibility' => ProductVisibility::Public,
            'price' => 10000,
            'category_id' => $subcategory->id,
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => $channelId,
            'fulfillment_source' => $channelCode->fulfillmentSource(),
        ];

        if ($channelCode === CommerceChannelCode::TzLocal && ! array_key_exists('store_id', $overrides)) {
            $defaults['store_id'] = $this->makeTzStore()->id;
        }

        $product = Product::factory()->create(array_merge($defaults, $overrides));

        Inventory::query()->firstOrCreate(
            [
                'product_id' => $product->id,
                'product_variant_id' => null,
            ],
            [
                'quantity' => 10,
                'reserved_quantity' => 0,
                'low_stock_threshold' => 2,
            ],
        );

        return $product->fresh(['inventory', 'variants']) ?? $product;
    }

    private function makeTzStore(): Store
    {
        return Store::query()->create([
            'code' => 'TZ'.strtoupper(substr((string) str()->uuid(), 0, 4)),
            'name' => 'Test TZ Store',
            'slug' => 'test-tz-store-'.str()->random(8),
            'is_active' => true,
        ]);
    }
}

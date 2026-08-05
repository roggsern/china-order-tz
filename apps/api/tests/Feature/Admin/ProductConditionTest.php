<?php

namespace Tests\Feature\Admin;

use App\Enums\CommerceChannelCode;
use App\Enums\ProductCondition;
use App\Enums\ProductLifecycleStatus;
use App\Models\Admin;
use App\Models\CatalogProductType;
use App\Models\CommerceChannel;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Orders\OrderSnapshotEngine;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\CatalogProductTypeSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CommerceChannelSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\SubcategorySeeder;
use Database\Support\CatalogAttributeDomainMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductConditionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CommerceChannelSeeder::class);
        $this->seed(DepartmentSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(SubcategorySeeder::class);
        $this->seed(CatalogProductTypeSeeder::class);

        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::CATALOG_CREATE,
                AdminPermissions::CATALOG_VIEW,
                AdminPermissions::CATALOG_UPDATE,
                AdminPermissions::CATALOG_PUBLISH,
                AdminPermissions::CONFIGURATION_VIEW,
            ])->create(),
        );
    }

    public function test_eligible_catalog_product_types_expose_supports_flag(): void
    {
        $iphone = CatalogProductType::query()->where('name', 'iPhone')->firstOrFail();
        $rice = CatalogProductType::query()->where('name', 'Rice')->firstOrFail();

        $this->assertTrue(CatalogAttributeDomainMap::supportsProductCondition('iPhone'));
        $this->assertFalse(CatalogAttributeDomainMap::supportsProductCondition('Rice'));

        $this->getJson('/api/v1/admin/catalog-product-types/'.$iphone->id)
            ->assertOk()
            ->assertJsonPath('data.supports_product_condition', true);

        $this->getJson('/api/v1/admin/catalog-product-types/'.$rice->id)
            ->assertOk()
            ->assertJsonPath('data.supports_product_condition', false);
    }

    public function test_create_eligible_product_defaults_to_brand_new(): void
    {
        $type = CatalogProductType::query()->where('name', 'iPhone')->firstOrFail();
        $channelId = CommerceChannel::query()->where('code', CommerceChannelCode::ChinaImport->value)->value('id');

        $response = $this->postJson('/api/v1/admin/products', [
            'name' => 'Condition iPhone',
            'catalog_product_type_id' => $type->id,
            'commerce_channel_id' => $channelId,
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
            'price' => 1000,
        ])->assertCreated();

        $response->assertJsonPath('data.product_condition', ProductCondition::BrandNew->value);
        $this->assertDatabaseHas('products', [
            'id' => $response->json('data.id'),
            'product_condition' => ProductCondition::BrandNew->value,
        ]);
    }

    public function test_create_eligible_product_accepts_refurbished(): void
    {
        $type = CatalogProductType::query()->where('name', 'Refrigerator')->firstOrFail();
        $channelId = CommerceChannel::query()->where('code', CommerceChannelCode::ChinaImport->value)->value('id');

        $this->postJson('/api/v1/admin/products', [
            'name' => 'Refurb Fridge',
            'catalog_product_type_id' => $type->id,
            'commerce_channel_id' => $channelId,
            'product_condition' => ProductCondition::Refurbished->value,
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
            'price' => 2000,
        ])
            ->assertCreated()
            ->assertJsonPath('data.product_condition', ProductCondition::Refurbished->value)
            ->assertJsonPath('data.product_condition_label', 'Refurbished');
    }

    public function test_non_eligible_product_ignores_submitted_condition(): void
    {
        $type = CatalogProductType::query()->where('name', 'Rice')->firstOrFail();
        $channelId = CommerceChannel::query()->where('code', CommerceChannelCode::ChinaImport->value)->value('id');

        $response = $this->postJson('/api/v1/admin/products', [
            'name' => 'Rice Bag',
            'catalog_product_type_id' => $type->id,
            'commerce_channel_id' => $channelId,
            'product_condition' => ProductCondition::Used->value,
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
            'price' => 10,
        ])->assertCreated();

        $response->assertJsonPath('data.product_condition', null);
        $this->assertDatabaseHas('products', [
            'id' => $response->json('data.id'),
            'product_condition' => null,
        ]);
    }

    public function test_invalid_condition_enum_is_rejected(): void
    {
        $type = CatalogProductType::query()->where('name', 'iPhone')->firstOrFail();
        $channelId = CommerceChannel::query()->where('code', CommerceChannelCode::ChinaImport->value)->value('id');

        $this->postJson('/api/v1/admin/products', [
            'name' => 'Bad Condition',
            'catalog_product_type_id' => $type->id,
            'commerce_channel_id' => $channelId,
            'product_condition' => 'LIKE_NEW',
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
            'price' => 100,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['product_condition']);
    }

    public function test_update_preserves_and_can_change_condition(): void
    {
        $type = CatalogProductType::query()->where('name', 'Gaming Laptop')->firstOrFail();
        $product = Product::factory()->chinaImport()->create([
            'catalog_product_type_id' => $type->id,
            'category_id' => $type->subcategory_id,
            'product_condition' => ProductCondition::OpenBox,
            'lifecycle_status' => ProductLifecycleStatus::Draft,
        ]);

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'name' => $product->name,
            'catalog_product_type_id' => $type->id,
            'product_condition' => ProductCondition::Used->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.product_condition', ProductCondition::Used->value);

        $this->assertSame(ProductCondition::Used, $product->fresh()->product_condition);
    }

    public function test_switching_to_non_eligible_type_clears_condition(): void
    {
        $laptop = CatalogProductType::query()->where('name', 'Business Laptop')->firstOrFail();
        $rice = CatalogProductType::query()->where('name', 'Rice')->firstOrFail();

        $product = Product::factory()->chinaImport()->create([
            'catalog_product_type_id' => $laptop->id,
            'category_id' => $laptop->subcategory_id,
            'product_condition' => ProductCondition::BrandNew,
            'lifecycle_status' => ProductLifecycleStatus::Draft,
        ]);

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'name' => $product->name,
            'catalog_product_type_id' => $rice->id,
            'product_condition' => ProductCondition::Refurbished->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.product_condition', null);

        $this->assertNull($product->fresh()->product_condition);
    }

    public function test_storefront_card_serializes_condition(): void
    {
        $type = CatalogProductType::query()->where('name', 'Smart Watch')->firstOrFail();
        $product = Product::factory()->chinaImport()->create([
            'catalog_product_type_id' => $type->id,
            'category_id' => $type->subcategory_id,
            'product_condition' => ProductCondition::OpenBox,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
            'visibility' => 'public',
        ]);

        $this->getJson('/api/v1/products?search='.urlencode($product->name))
            ->assertOk();

        // Detail endpoint is more stable for exact product match
        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('data.product_condition', ProductCondition::OpenBox->value)
            ->assertJsonPath('data.product_condition_label', 'Open Box');
    }

    public function test_order_snapshot_freezes_product_condition(): void
    {
        $type = CatalogProductType::query()->where('name', 'Electric Drill')->firstOrFail();
        $product = Product::factory()->chinaImport()->create([
            'catalog_product_type_id' => $type->id,
            'category_id' => $type->subcategory_id,
            'product_condition' => ProductCondition::Refurbished,
        ]);

        $payload = app(OrderSnapshotEngine::class)->snapshotFromCatalog(
            $product,
            null,
            1,
            '15000.00',
        );

        $this->assertSame(ProductCondition::Refurbished->value, $payload['product_condition_snapshot']);

        $item = OrderItem::factory()->create(array_merge($payload, [
            'order_id' => Order::factory()->create()->id,
        ]));

        $this->assertSame(
            ProductCondition::Refurbished->value,
            $item->fresh()->product_condition_snapshot,
        );

        $product->update(['product_condition' => ProductCondition::BrandNew]);
        $this->assertSame(
            ProductCondition::Refurbished->value,
            $item->fresh()->product_condition_snapshot,
        );
    }

    public function test_list_products_supports_condition_filter(): void
    {
        $type = CatalogProductType::query()->where('name', 'iPhone')->firstOrFail();

        Product::factory()->chinaImport()->create([
            'name' => 'Filter New Phone',
            'catalog_product_type_id' => $type->id,
            'category_id' => $type->subcategory_id,
            'product_condition' => ProductCondition::BrandNew,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
        ]);
        Product::factory()->chinaImport()->create([
            'name' => 'Filter Used Phone',
            'catalog_product_type_id' => $type->id,
            'category_id' => $type->subcategory_id,
            'product_condition' => ProductCondition::Used,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/products?product_condition=USED')
            ->assertOk();

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Filter Used Phone'));
        $this->assertFalse($names->contains('Filter New Phone'));
    }

    public function test_condition_is_not_a_catalog_attribute_slug(): void
    {
        $this->assertDatabaseMissing('catalog_attributes', ['slug' => 'product-condition']);
        $this->assertDatabaseMissing('catalog_attributes', ['slug' => 'condition']);
    }

    public function test_medical_and_grocery_types_are_not_eligible(): void
    {
        foreach (['Cotton Wool', 'Syringe', 'Rice', 'Cooking Oil', 'Baby Diaper', 'Foundation'] as $name) {
            $this->assertFalse(
                CatalogAttributeDomainMap::supportsProductCondition($name),
                "[{$name}] must not be product-condition eligible.",
            );
        }
    }

    public function test_eligible_legacy_null_product_card_serializes_brand_new(): void
    {
        $type = CatalogProductType::query()->where('name', 'iPhone')->firstOrFail();
        $product = Product::factory()->chinaImport()->create([
            'name' => 'Legacy Null Card Phone',
            'catalog_product_type_id' => $type->id,
            'category_id' => $type->subcategory_id,
            'product_condition' => null,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
            'visibility' => 'public',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'product_condition' => null,
        ]);

        $response = $this->getJson('/api/v1/products?search='.urlencode($product->name))
            ->assertOk();

        $card = collect($response->json('data'))->firstWhere('id', $product->id);
        $this->assertNotNull($card);
        $this->assertSame(ProductCondition::BrandNew->value, $card['product_condition']);
        $this->assertSame('Brand New', $card['product_condition_label']);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'product_condition' => null,
        ]);
    }

    public function test_eligible_legacy_null_product_detail_serializes_brand_new(): void
    {
        $type = CatalogProductType::query()->where('name', 'iPhone')->firstOrFail();
        $product = Product::factory()->chinaImport()->create([
            'catalog_product_type_id' => $type->id,
            'category_id' => $type->subcategory_id,
            'product_condition' => null,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
            'visibility' => 'public',
        ]);

        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('data.product_condition', ProductCondition::BrandNew->value)
            ->assertJsonPath('data.product_condition_label', 'Brand New');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'product_condition' => null,
        ]);
    }

    public function test_eligible_legacy_null_order_creation_snapshots_brand_new(): void
    {
        $type = CatalogProductType::query()->where('name', 'Electric Drill')->firstOrFail();
        $product = Product::factory()->chinaImport()->create([
            'catalog_product_type_id' => $type->id,
            'category_id' => $type->subcategory_id,
            'product_condition' => null,
        ]);

        $payload = app(OrderSnapshotEngine::class)->snapshotFromCatalog(
            $product,
            null,
            1,
            '15000.00',
        );

        $this->assertSame(ProductCondition::BrandNew->value, $payload['product_condition_snapshot']);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'product_condition' => null,
        ]);
    }

    public function test_non_eligible_null_stays_null_and_badge_hidden(): void
    {
        $type = CatalogProductType::query()->where('name', 'Rice')->firstOrFail();
        $product = Product::factory()->chinaImport()->create([
            'catalog_product_type_id' => $type->id,
            'category_id' => $type->subcategory_id,
            'product_condition' => null,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
            'visibility' => 'public',
        ]);

        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('data.product_condition', null)
            ->assertJsonPath('data.product_condition_label', null);

        $this->getJson('/api/v1/admin/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.product_condition', null)
            ->assertJsonPath('data.product_condition_eligible', false);
    }

    public function test_switching_non_eligible_to_eligible_defaults_brand_new(): void
    {
        $rice = CatalogProductType::query()->where('name', 'Rice')->firstOrFail();
        $iphone = CatalogProductType::query()->where('name', 'iPhone')->firstOrFail();

        $product = Product::factory()->chinaImport()->create([
            'catalog_product_type_id' => $rice->id,
            'category_id' => $rice->subcategory_id,
            'product_condition' => null,
            'lifecycle_status' => ProductLifecycleStatus::Draft,
        ]);

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'name' => $product->name,
            'catalog_product_type_id' => $iphone->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.product_condition', ProductCondition::BrandNew->value)
            ->assertJsonPath('data.product_condition_eligible', true);

        $this->assertSame(ProductCondition::BrandNew, $product->fresh()->product_condition);
    }

    public function test_admin_exposes_effective_brand_new_for_eligible_legacy_null(): void
    {
        $type = CatalogProductType::query()->where('name', 'iPhone')->firstOrFail();
        $product = Product::factory()->chinaImport()->create([
            'catalog_product_type_id' => $type->id,
            'category_id' => $type->subcategory_id,
            'product_condition' => null,
            'lifecycle_status' => ProductLifecycleStatus::Draft,
        ]);

        $this->getJson('/api/v1/admin/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.product_condition', ProductCondition::BrandNew->value)
            ->assertJsonPath('data.product_condition_label', 'Brand New')
            ->assertJsonPath('data.product_condition_eligible', true);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'product_condition' => null,
        ]);
    }
}

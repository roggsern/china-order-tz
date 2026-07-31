<?php

namespace Tests\Feature\Admin;

use App\Enums\CatalogAttributeType;
use App\Enums\CatalogOrigin;
use App\Models\Admin;
use App\Models\Brand;
use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use App\Models\CatalogProductAttributeValue;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Department;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductImage;
use App\Models\ProductMedia;
use App\Models\ProductShippingOption;
use App\Models\ProductType;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttributeValue;
use App\Models\Supplier;
use Database\Seeders\ProductTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\MinimalTestImage;
use Tests\TestCase;

class AdminCatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_category_tree_and_restore(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $department = Department::factory()->create([
            'name' => 'Electronics Dept',
            'slug' => 'electronics-dept',
        ]);

        $create = $this->postJson('/api/v1/admin/categories', [
            'name' => 'Electronics Root',
            'department_id' => $department->id,
            'origin' => 'china',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.origin', 'china')
            ->assertJsonPath('data.department_id', $department->id);
        $rootId = $create->json('data.id');

        $child = $this->postJson('/api/v1/admin/categories', [
            'name' => 'Phones Leaf',
            'department_id' => $department->id,
            'parent_id' => $rootId,
            'origin' => 'china',
        ]);

        $child->assertCreated()
            ->assertJsonPath('data.parent_id', $rootId)
            ->assertJsonPath('data.department_id', $department->id);

        $this->getJson('/api/v1/admin/categories?department_id='.$department->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['id' => $rootId]);

        $this->deleteJson('/api/v1/admin/categories/'.$rootId)
            ->assertStatus(422);

        $this->deleteJson('/api/v1/admin/categories/'.$child->json('data.id'))
            ->assertOk();

        $this->postJson('/api/v1/admin/categories/'.$child->json('data.id').'/restore')
            ->assertOk()
            ->assertJsonPath('data.name', 'Phones Leaf')
            ->assertJsonPath('data.department.id', $department->id);
    }

    public function test_admin_can_manage_brands_and_category_links(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $category = Category::factory()->create([
            'origin' => CatalogOrigin::China,
            'name' => 'Link Target',
        ]);

        $create = $this->postJson('/api/v1/admin/brands', [
            'name' => 'Managed Brand',
            'description' => 'Independent brand',
            'country' => 'TZ',
            'is_featured' => true,
            'sort_order' => 5,
            'is_active' => true,
            'category_ids' => [$category->id],
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Managed Brand')
            ->assertJsonPath('data.country', 'TZ')
            ->assertJsonPath('data.is_featured', true)
            ->assertJsonPath('data.sort_order', 5)
            ->assertJsonPath('data.category_ids.0', $category->id);

        $brandId = $create->json('data.id');

        $this->getJson('/api/v1/admin/brands?search=Managed&is_featured=1')
            ->assertOk()
            ->assertJsonFragment(['id' => $brandId]);

        $this->putJson('/api/v1/admin/brands/'.$brandId, [
            'name' => 'Managed Brand',
            'is_featured' => false,
            'is_active' => false,
            'sort_order' => 10,
            'banner' => 'https://cdn.example.com/banner.jpg',
            'logo' => 'https://cdn.example.com/logo.png',
        ])
            ->assertOk()
            ->assertJsonPath('data.is_featured', false)
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.sort_order', 10)
            ->assertJsonPath('data.banner', 'https://cdn.example.com/banner.jpg');

        $this->putJson('/api/v1/admin/brands/'.$brandId.'/categories', [
            'category_ids' => [],
        ])
            ->assertOk()
            ->assertJsonPath('data.category_ids', []);

        $this->putJson('/api/v1/admin/brands/'.$brandId.'/categories', [
            'category_ids' => [$category->id],
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.category_ids');

        $this->deleteJson('/api/v1/admin/brands/'.$brandId)->assertOk();

        $this->postJson('/api/v1/admin/brands/'.$brandId.'/restore')
            ->assertOk()
            ->assertJsonPath('data.name', 'Managed Brand');

        $this->assertTrue(Brand::query()->whereKey($brandId)->exists());
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasColumn('brands', 'department_id'),
            'Brands must remain shared and must not attach directly to departments.',
        );
    }

    public function test_admin_can_manage_departments(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $create = $this->postJson('/api/v1/admin/departments', [
            'name' => 'Test Department',
            'icon' => '📦',
            'description' => 'A managed department',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Test Department')
            ->assertJsonPath('data.slug', 'test-department')
            ->assertJsonPath('data.sort_order', 10)
            ->assertJsonPath('data.is_active', true);

        $departmentId = $create->json('data.id');

        $this->getJson('/api/v1/admin/departments?search=Test')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['id' => $departmentId]);

        $this->putJson('/api/v1/admin/departments/'.$departmentId, [
            'name' => 'Updated Department',
            'icon' => '🛍️',
            'is_active' => false,
            'sort_order' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Department')
            ->assertJsonPath('data.slug', 'updated-department')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.sort_order', 5);

        $this->deleteJson('/api/v1/admin/departments/'.$departmentId)->assertOk();

        $this->assertSoftDeleted('departments', ['id' => $departmentId]);

        $this->postJson('/api/v1/admin/departments/'.$departmentId.'/restore')
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Department');

        $this->assertTrue(Department::query()->whereKey($departmentId)->exists());
    }

    public function test_admin_can_manage_subcategories_via_parent_id(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $department = Department::factory()->create([
            'name' => 'Audio Dept',
            'slug' => 'audio-dept',
        ]);

        $category = Category::factory()->forDepartment($department)->create([
            'name' => 'Mixers',
            'slug' => 'audio-dept-mixers',
            'parent_id' => null,
        ]);

        $create = $this->postJson('/api/v1/admin/subcategories', [
            'name' => 'Digital Mixers',
            'category_id' => $category->id,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Digital Mixers')
            ->assertJsonPath('data.category_id', $category->id)
            ->assertJsonPath('data.department_id', $department->id)
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.department.id', $department->id);

        $subcategoryId = $create->json('data.id');

        $this->getJson('/api/v1/admin/subcategories?category_id='.$category->id)
            ->assertOk()
            ->assertJsonFragment(['id' => $subcategoryId]);

        $this->getJson('/api/v1/admin/subcategories?department_id='.$department->id)
            ->assertOk()
            ->assertJsonFragment(['id' => $subcategoryId]);

        $this->putJson('/api/v1/admin/subcategories/'.$subcategoryId, [
            'name' => 'Pro Digital Mixers',
            'category_id' => $category->id,
            'is_active' => false,
            'sort_order' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Pro Digital Mixers')
            ->assertJsonPath('data.is_active', false);

        $this->deleteJson('/api/v1/admin/subcategories/'.$subcategoryId)->assertOk();

        $this->assertSoftDeleted('categories', ['id' => $subcategoryId]);

        $this->postJson('/api/v1/admin/subcategories/'.$subcategoryId.'/restore')
            ->assertOk()
            ->assertJsonPath('data.name', 'Pro Digital Mixers')
            ->assertJsonPath('data.category_id', $category->id);

        $this->assertTrue(Category::query()->whereKey($subcategoryId)->whereNotNull('parent_id')->exists());
    }

    public function test_admin_can_manage_catalog_product_types(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $department = Department::factory()->create([
            'name' => 'Fashion Dept',
            'slug' => 'fashion-dept',
        ]);

        $category = Category::factory()->forDepartment($department)->create([
            'name' => 'Clothing',
            'slug' => 'fashion-dept-clothing',
            'parent_id' => null,
        ]);

        $subcategory = Category::factory()->forDepartment($department)->create([
            'name' => 'T-Shirts',
            'slug' => 'fashion-dept-clothing-t-shirts',
            'parent_id' => $category->id,
        ]);

        $create = $this->postJson('/api/v1/admin/catalog-product-types', [
            'name' => 'Round Neck T-Shirt',
            'subcategory_id' => $subcategory->id,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Round Neck T-Shirt')
            ->assertJsonPath('data.subcategory_id', $subcategory->id)
            ->assertJsonPath('data.subcategory.id', $subcategory->id)
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.department.id', $department->id);

        $typeId = $create->json('data.id');

        $this->getJson('/api/v1/admin/catalog-product-types?subcategory_id='.$subcategory->id)
            ->assertOk()
            ->assertJsonFragment(['id' => $typeId]);

        $this->getJson('/api/v1/admin/catalog-product-types?department_id='.$department->id)
            ->assertOk()
            ->assertJsonFragment(['id' => $typeId]);

        $this->getJson('/api/v1/admin/catalog-product-types?category_id='.$category->id)
            ->assertOk()
            ->assertJsonFragment(['id' => $typeId]);

        $this->putJson('/api/v1/admin/catalog-product-types/'.$typeId, [
            'name' => 'Classic Round Neck',
            'subcategory_id' => $subcategory->id,
            'is_active' => false,
            'sort_order' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Classic Round Neck')
            ->assertJsonPath('data.is_active', false);

        $this->deleteJson('/api/v1/admin/catalog-product-types/'.$typeId)->assertOk();

        $this->assertSoftDeleted('catalog_product_types', ['id' => $typeId]);

        $this->postJson('/api/v1/admin/catalog-product-types/'.$typeId.'/restore')
            ->assertOk()
            ->assertJsonPath('data.name', 'Classic Round Neck')
            ->assertJsonPath('data.department.id', $department->id);

        $this->assertTrue(CatalogProductType::query()->whereKey($typeId)->exists());
    }

    public function test_admin_can_manage_catalog_attributes_options_and_mappings(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $create = $this->postJson('/api/v1/admin/catalog-attributes', [
            'name' => 'RAM',
            'type' => 'select',
            'unit' => 'GB',
            'is_filterable' => true,
            'is_active' => true,
            'options' => [
                ['value' => '8GB', 'sort_order' => 1],
                ['value' => '12GB', 'sort_order' => 2],
            ],
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'RAM')
            ->assertJsonPath('data.type', 'select')
            ->assertJsonPath('data.is_filterable', true)
            ->assertJsonCount(2, 'data.options');

        $attributeId = $create->json('data.id');

        $option = $this->postJson('/api/v1/admin/catalog-attributes/'.$attributeId.'/options', [
            'value' => '16GB',
            'sort_order' => 3,
        ]);

        $option->assertCreated()->assertJsonPath('data.value', '16GB');
        $optionId = $option->json('data.id');

        $this->putJson('/api/v1/admin/catalog-attribute-options/'.$optionId, [
            'value' => '16 GB',
            'sort_order' => 3,
        ])
            ->assertOk()
            ->assertJsonPath('data.value', '16 GB');

        $department = Department::factory()->create(['slug' => 'attr-dept']);
        $category = Category::factory()->forDepartment($department)->create([
            'name' => 'Phones',
            'slug' => 'attr-dept-phones',
            'parent_id' => null,
        ]);
        $subcategory = Category::factory()->forDepartment($department)->create([
            'name' => 'Smartphones',
            'slug' => 'attr-dept-phones-smartphones',
            'parent_id' => $category->id,
        ]);
        $productType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'name' => 'Android Smartphone',
            'slug' => 'attr-android-smartphone',
        ]);

        $this->putJson('/api/v1/admin/catalog-product-types/'.$productType->id.'/attributes', [
            'attributes' => [
                [
                    'catalog_attribute_id' => $attributeId,
                    'is_required' => true,
                    'sort_order' => 1,
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.attributes')
            ->assertJsonPath('data.attributes.0.id', $attributeId);

        $this->getJson('/api/v1/admin/catalog-attributes/filters?catalog_product_type_id='.$productType->id)
            ->assertOk()
            ->assertJsonFragment(['id' => $attributeId]);

        $this->deleteJson('/api/v1/admin/catalog-attribute-options/'.$optionId)->assertOk();
        $this->assertDatabaseMissing('catalog_attribute_options', ['id' => $optionId]);

        $this->deleteJson('/api/v1/admin/catalog-attributes/'.$attributeId)->assertOk();
        $this->assertSoftDeleted('catalog_attributes', ['id' => $attributeId]);

        $this->postJson('/api/v1/admin/catalog-attributes/'.$attributeId.'/restore')
            ->assertOk()
            ->assertJsonPath('data.name', 'RAM');

        $this->assertTrue(CatalogAttribute::query()->whereKey($attributeId)->exists());
    }

    public function test_admin_can_manage_product_core(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $department = Department::factory()->create(['slug' => 'core-phones']);
        $category = Category::factory()->forDepartment($department)->create([
            'name' => 'Mobiles',
            'slug' => 'core-mobiles',
            'parent_id' => null,
        ]);
        $subcategory = Category::factory()->forDepartment($department)->create([
            'name' => 'Smartphones',
            'slug' => 'core-smartphones',
            'parent_id' => $category->id,
        ]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'name' => 'Android Smartphone',
            'slug' => 'core-android-smartphone',
        ]);
        $brand = Brand::factory()->create([
            'name' => 'Core Phone Brand',
            'slug' => 'core-phone-brand',
        ]);

        $create = $this->postJson('/api/v1/admin/products', [
            'name' => 'Core Galaxy Phone',
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => CommerceChannel::query()->where('code', 'CHINA_IMPORT')->value('id'),
            'supplier_id' => $this->chinaSupplierId(),
            'brand_id' => $brand->id,
            'short_description' => 'Product core sample',
            'description' => 'Full description',
            'price' => 250000,
            'stock_quantity' => 3,
            'air_shipping_price' => 8000,
            'status' => 'active',
            'visibility' => 'public',
            'is_featured' => true,
            'sort_order' => 3,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Core Galaxy Phone')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.visibility', 'public')
            ->assertJsonPath('data.is_featured', true)
            ->assertJsonPath('data.sort_order', 3)
            ->assertJsonPath('data.catalog_product_type_id', $catalogType->id)
            ->assertJsonPath('data.category.id', $subcategory->id)
            ->assertJsonPath('data.brand.id', $brand->id);

        $productId = $create->json('data.id');

        $this->getJson('/api/v1/admin/products?department_id='.$department->id.'&featured=1&search=Galaxy')
            ->assertOk()
            ->assertJsonFragment(['id' => $productId]);

        $this->getJson('/api/v1/admin/products?catalog_product_type_id='.$catalogType->id.'&status=active')
            ->assertOk()
            ->assertJsonFragment(['id' => $productId]);

        $this->putJson('/api/v1/admin/products/'.$productId, [
            'name' => 'Core Galaxy Phone',
            'is_active' => false,
            'is_featured' => false,
            'visibility' => 'private',
            'status' => 'draft',
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.is_featured', false)
            ->assertJsonPath('data.visibility', 'private')
            ->assertJsonPath('data.status', 'draft');

        $this->assertTrue(Product::query()->whereKey($productId)->exists());
        $this->assertTrue(Schema::hasColumn('products', 'catalog_product_type_id'));
        $this->assertFalse(Schema::hasColumn('products', 'department_id'));

        $this->deleteJson('/api/v1/admin/products/'.$productId)->assertOk();
        $this->assertSoftDeleted('products', ['id' => $productId]);

        $this->postJson('/api/v1/admin/products/'.$productId.'/restore')
            ->assertOk()
            ->assertJsonPath('data.name', 'Core Galaxy Phone');

        $this->assertTrue(Product::query()->whereKey($productId)->exists());
        $this->assertFalse(Product::query()->whereKey($productId)->featured()->exists());
        $this->assertTrue(Product::query()->whereKey($productId)->draft()->exists());
    }

    public function test_canonical_simple_product_stock_update_via_patch_endpoint(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $department = Department::factory()->create(['slug' => 'stock-dept']);
        $category = Category::factory()->forDepartment($department)->create([
            'name' => 'Stock Mobiles',
            'slug' => 'stock-mobiles',
            'parent_id' => null,
        ]);
        $subcategory = Category::factory()->forDepartment($department)->create([
            'name' => 'Stock Smartphones',
            'slug' => 'stock-smartphones',
            'parent_id' => $category->id,
        ]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'name' => 'Stock Android Phone',
            'slug' => 'stock-android-phone',
        ]);

        $create = $this->postJson('/api/v1/admin/products', [
            'name' => 'Stock Galaxy Phone',
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => CommerceChannel::query()->where('code', 'CHINA_IMPORT')->value('id'),
            'supplier_id' => $this->chinaSupplierId(),
            'price' => 250000,
            'stock_quantity' => 3,
            'status' => 'draft',
        ]);

        $create->assertCreated();
        $productId = $create->json('data.id');

        $this->getJson('/api/v1/admin/products/'.$productId)
            ->assertOk()
            ->assertJsonPath('data.inventory.0.quantity', 3)
            ->assertJsonPath('data.inventory.0.available_quantity', 3);

        $update = $this->patchJson('/api/v1/admin/products/'.$productId.'/stock', [
            'stock_quantity' => 10,
        ]);

        $update->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.inventory.0.quantity', 10)
            ->assertJsonPath('data.inventory.0.available_quantity', 10);

        $inventory = Inventory::query()
            ->where('product_id', $productId)
            ->whereNull('product_variant_id')
            ->first();

        $this->assertNotNull($inventory);
        $this->assertSame(10, (int) $inventory->quantity);
        $this->assertSame(0, (int) $inventory->reserved_quantity);
    }

    public function test_admin_product_response_exposes_legacy_configuration_product_flag(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $department = Department::factory()->create(['slug' => 'legacy-flag-dept']);
        $category = Category::factory()->forDepartment($department)->create([
            'name' => 'Legacy Flag Mobiles',
            'slug' => 'legacy-flag-mobiles',
            'parent_id' => null,
        ]);
        $subcategory = Category::factory()->forDepartment($department)->create([
            'name' => 'Legacy Flag Smartphones',
            'slug' => 'legacy-flag-smartphones',
            'parent_id' => $category->id,
        ]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'name' => 'Legacy Flag Phone',
            'slug' => 'legacy-flag-phone',
        ]);

        $simpleCreate = $this->postJson('/api/v1/admin/products', [
            'name' => 'Simple Catalog Phone',
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => CommerceChannel::query()->where('code', 'CHINA_IMPORT')->value('id'),
            'supplier_id' => $this->chinaSupplierId(),
            'price' => 250000,
            'stock_quantity' => 5,
            'status' => 'draft',
        ]);
        $simpleCreate->assertCreated()
            ->assertJsonPath('data.legacy_configuration_product', false);

        $simpleId = $simpleCreate->json('data.id');

        $this->getJson('/api/v1/admin/products/'.$simpleId)
            ->assertOk()
            ->assertJsonPath('data.legacy_configuration_product', false)
            ->assertJsonPath('data.name', 'Simple Catalog Phone');

        $this->seed(ProductTypeSeeder::class);
        $phones = ProductType::query()->where('slug', 'phones')->firstOrFail();
        $legacyCategory = Category::factory()->forDepartment($department)->create([
            'product_type_id' => $phones->id,
            'parent_id' => $category->id,
        ]);
        $legacyCatalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $legacyCategory->id,
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

        $legacyCreate = $this->postJson('/api/v1/admin/products', [
            'name' => 'Legacy Config Phone',
            'category_id' => $legacyCategory->id,
            'catalog_product_type_id' => $legacyCatalogType->id,
            'commerce_channel_id' => CommerceChannel::query()->where('code', 'CHINA_IMPORT')->value('id'),
            'supplier_id' => $this->chinaSupplierId(),
            'sku' => 'LEGACY-FLAG-1',
            'price' => 500000,
            'air_shipping_price' => 8000,
            'stock_quantity' => 0,
            'status' => true,
            'configurations' => [
                [
                    'attribute_value_ids' => [$storage128->id, $black->id, $conditionNew->id],
                    'sku' => 'LEGACY-FLAG-1-128-BLACK-NEW',
                    'stock_quantity' => 4,
                    'price' => 520000,
                ],
            ],
        ]);

        $legacyCreate->assertCreated()
            ->assertJsonPath('data.legacy_configuration_product', true);

        $legacyId = $legacyCreate->json('data.id');

        $this->getJson('/api/v1/admin/products/'.$legacyId)
            ->assertOk()
            ->assertJsonPath('data.legacy_configuration_product', true);

        $this->getJson('/api/v1/admin/products?search=Simple')
            ->assertOk()
            ->assertJsonFragment(['id' => $simpleId, 'legacy_configuration_product' => false]);

        $this->getJson('/api/v1/admin/products?search=Legacy+Config')
            ->assertOk()
            ->assertJsonFragment(['id' => $legacyId, 'legacy_configuration_product' => true]);
    }

    public function test_canonical_simple_product_price_update_allows_activation(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $department = Department::factory()->create(['slug' => 'pricing-dept']);
        $category = Category::factory()->forDepartment($department)->create([
            'name' => 'Pricing Mobiles',
            'slug' => 'pricing-mobiles',
            'parent_id' => null,
        ]);
        $subcategory = Category::factory()->forDepartment($department)->create([
            'name' => 'Pricing Smartphones',
            'slug' => 'pricing-smartphones',
            'parent_id' => $category->id,
        ]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'name' => 'Pricing Android Phone',
            'slug' => 'pricing-android-phone',
        ]);

        $create = $this->postJson('/api/v1/admin/products', [
            'name' => 'Canonical Pricing Phone',
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => CommerceChannel::query()->where('code', 'CHINA_IMPORT')->value('id'),
            'supplier_id' => $this->chinaSupplierId(),
            'status' => 'draft',
            'visibility' => 'public',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        $productId = $create->json('data.id');

        $this->putJson('/api/v1/admin/products/'.$productId, [
            'name' => 'Canonical Pricing Phone',
            'catalog_product_type_id' => $catalogType->id,
            'price' => 175000,
            'air_shipping_price' => 8000,
            'stock_quantity' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('data.price', '175000.00');

        $this->assertSame('175000.00', (string) Product::query()->whereKey($productId)->value('price'));

        $this->putJson('/api/v1/admin/products/'.$productId, [
            'name' => 'Canonical Pricing Phone',
            'catalog_product_type_id' => $catalogType->id,
            'price' => 175000,
            'status' => 'active',
            'visibility' => 'public',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.price', '175000.00');
    }

    public function test_lifecycle_status_change_syncs_is_active_without_explicit_is_active_payload(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $department = Department::factory()->create(['slug' => 'lifecycle-dept']);
        $category = Category::factory()->forDepartment($department)->create([
            'name' => 'Lifecycle Mobiles',
            'slug' => 'lifecycle-mobiles',
            'parent_id' => null,
        ]);
        $subcategory = Category::factory()->forDepartment($department)->create([
            'name' => 'Lifecycle Smartphones',
            'slug' => 'lifecycle-smartphones',
            'parent_id' => $category->id,
        ]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'name' => 'Lifecycle Android Phone',
            'slug' => 'lifecycle-android-phone',
        ]);

        $productId = $this->postJson('/api/v1/admin/products', [
            'name' => 'Lifecycle Alignment Phone',
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => CommerceChannel::query()->where('code', 'CHINA_IMPORT')->value('id'),
            'supplier_id' => $this->chinaSupplierId(),
            'price' => 200000,
            'air_shipping_price' => 8000,
            'stock_quantity' => 3,
            'status' => 'active',
            'visibility' => 'public',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->json('data.id');

        $this->assertTrue(Product::query()->whereKey($productId)->value('is_active'));

        $this->putJson('/api/v1/admin/products/'.$productId, [
            'name' => 'Lifecycle Alignment Phone',
            'catalog_product_type_id' => $catalogType->id,
            'status' => 'draft',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->assertFalse(Product::query()->whereKey($productId)->value('is_active'));

        $this->putJson('/api/v1/admin/products/'.$productId, [
            'name' => 'Lifecycle Alignment Phone',
            'catalog_product_type_id' => $catalogType->id,
            'price' => 200000,
            'status' => 'active',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertTrue(Product::query()->whereKey($productId)->value('is_active'));

        $this->putJson('/api/v1/admin/products/'.$productId, [
            'name' => 'Lifecycle Alignment Phone',
            'catalog_product_type_id' => $catalogType->id,
            'status' => 'archived',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->assertFalse(Product::query()->whereKey($productId)->value('is_active'));
    }

    public function test_canonical_shipping_sync_loads_and_updates_china_import_product(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->fromChina()->create([
            'fulfillment_source' => 'imported_from_china',
            'air_shipping_price' => null,
            'sea_shipping_price' => null,
        ]);

        ProductShippingOption::withTrashed()
            ->where('product_id', $product->id)
            ->forceDelete();

        $this->getJson('/api/v1/admin/products/'.$product->id.'/shipping-options')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->putJson('/api/v1/admin/products/'.$product->id.'/shipping-options/sync', [
            'shipping_options' => [
                [
                    'transport_mode' => 'air',
                    'price' => 11000,
                    'currency' => 'TZS',
                    'is_available' => true,
                    'notes' => 'Canonical air',
                    'sort_order' => 0,
                ],
                [
                    'transport_mode' => 'sea',
                    'price' => 4200,
                    'currency' => 'TZS',
                    'is_available' => true,
                    'notes' => 'Canonical sea',
                    'sort_order' => 1,
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.transport_mode', 'air')
            ->assertJsonPath('data.1.transport_mode', 'sea');

        $product->refresh();
        $this->assertSame('11000.00', (string) $product->air_shipping_price);
        $this->assertSame('4200.00', (string) $product->sea_shipping_price);

        $this->getJson('/api/v1/admin/products/'.$product->id.'/shipping-options')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.notes', 'Canonical air');
    }

    public function test_admin_can_manage_product_media(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->create([
            'name' => 'Media Test Product',
            'slug' => 'media-test-product',
        ]);

        $upload = $this->postJson('/api/v1/admin/products/'.$product->id.'/media', [
            'type' => 'image',
            'url' => '/storage/demo-products/phone.jpg',
            'alt_text' => 'Main shot',
            'title' => 'Primary',
            'is_primary' => true,
        ]);

        $upload->assertCreated()
            ->assertJsonPath('data.type', 'image')
            ->assertJsonPath('data.is_primary', true)
            ->assertJsonPath('data.alt_text', 'Main shot')
            ->assertJsonPath('data.is_legacy', false);

        $mediaId = $upload->json('data.id');

        $video = $this->postJson('/api/v1/admin/products/'.$product->id.'/media', [
            'type' => 'video',
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'title' => 'Demo video',
        ]);

        $video->assertCreated()
            ->assertJsonPath('data.type', 'video')
            ->assertJsonPath('data.is_primary', false);

        $second = $this->postJson('/api/v1/admin/products/'.$product->id.'/media', [
            'type' => 'image',
            'url' => '/storage/demo-products/shoes.jpg',
            'sort_order' => 2,
        ]);
        $second->assertCreated();
        $secondId = $second->json('data.id');

        $this->postJson('/api/v1/admin/products/'.$product->id.'/media/'.$secondId.'/primary')
            ->assertOk()
            ->assertJsonPath('data.is_primary', true);

        $this->assertFalse(ProductMedia::query()->whereKey($mediaId)->value('is_primary'));

        $this->putJson('/api/v1/admin/products/'.$product->id.'/media/'.$secondId, [
            'sort_order' => 0,
            'is_active' => false,
            'alt_text' => 'Updated alt',
        ])
            ->assertOk()
            ->assertJsonPath('data.sort_order', 0)
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.alt_text', 'Updated alt');

        $this->getJson('/api/v1/admin/products/'.$product->id.'/media')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->assertTrue($product->fresh()->media()->exists());
        $this->assertTrue($product->fresh()->videos()->exists());
        $this->assertNotNull($product->fresh()->primaryMedia());

        $this->deleteJson('/api/v1/admin/products/'.$product->id.'/media/'.$mediaId)->assertOk();
        $this->assertSoftDeleted('product_media', ['id' => $mediaId]);
    }

    public function test_admin_product_media_returns_catalog_media_when_active_catalog_exists(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->create([
            'name' => 'Catalog Media Product',
            'slug' => 'catalog-media-product',
        ]);

        ProductImage::factory()->create([
            'product_id' => $product->id,
            'path' => 'demo-products/phone.jpg',
            'alt_text' => 'Legacy image',
        ]);

        $catalogMedia = ProductMedia::factory()->primary()->create([
            'product_id' => $product->id,
            'url' => '/storage/demo-products/shoes.jpg',
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/admin/products/'.$product->id.'/media')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $catalogMedia->id)
            ->assertJsonPath('data.0.is_legacy', false);
    }

    public function test_admin_product_media_read_bridge_returns_legacy_images_without_writes(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->create([
            'name' => 'Legacy Media Product',
            'slug' => 'legacy-media-product',
        ]);

        $primaryPath = 'demo-products/phone.jpg';
        $secondaryPath = 'demo-products/shoes.jpg';
        Storage::disk('public')->put($primaryPath, 'fake-image');
        Storage::disk('public')->put($secondaryPath, 'fake-image');

        ProductImage::factory()->primary()->create([
            'product_id' => $product->id,
            'path' => $primaryPath,
            'alt_text' => 'Legacy primary',
            'sort_order' => 0,
        ]);
        ProductImage::factory()->create([
            'product_id' => $product->id,
            'path' => $secondaryPath,
            'alt_text' => 'Legacy secondary',
            'sort_order' => 1,
        ]);

        $this->assertSame(0, ProductMedia::query()->count());

        $response = $this->getJson('/api/v1/admin/products/'.$product->id.'/media');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'image')
            ->assertJsonPath('data.0.is_legacy', true)
            ->assertJsonPath('data.0.is_primary', true)
            ->assertJsonPath('data.0.alt_text', 'Legacy primary')
            ->assertJsonPath('data.0.url', Storage::disk('public')->url($primaryPath))
            ->assertJsonPath('data.1.is_legacy', true)
            ->assertJsonPath('data.1.alt_text', 'Legacy secondary');

        $this->assertSame(0, ProductMedia::query()->count());
        $this->assertSame(2, $product->fresh()->images()->count());
    }

    public function test_catalog_file_upload_creates_product_media_only(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->create([
            'name' => 'Catalog Upload Product',
            'slug' => 'catalog-upload-product',
        ]);

        $response = $this->post('/api/v1/admin/products/'.$product->id.'/media', [
            'type' => 'image',
            'file' => MinimalTestImage::jpeg('catalog-upload.jpg'),
            'alt_text' => 'Catalog upload',
            'title' => 'Catalog upload title',
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'image')
            ->assertJsonPath('data.alt_text', 'Catalog upload')
            ->assertJsonPath('data.is_legacy', false);

        $this->assertSame(0, ProductImage::query()->count());
        $this->assertSame(1, ProductMedia::query()->count());

        $media = ProductMedia::query()->firstOrFail();

        $this->assertSame($product->id, $media->product_id);
        $this->assertSame('Catalog upload', $media->alt_text);
        $this->assertSame($response->json('data.url'), $media->url);
        $this->assertNotEmpty($media->url);
    }

    public function test_legacy_image_upload_endpoint_creates_product_media_only(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->create([
            'name' => 'Legacy Upload Product',
            'slug' => 'legacy-upload-product',
        ]);

        $response = $this->post('/api/v1/admin/products/'.$product->id.'/images', [
            'image' => MinimalTestImage::jpeg('legacy-upload.jpg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.source', 'product_media')
            ->assertJsonStructure(['data' => ['id', 'path', 'url', 'media_id', 'source']]);

        $this->assertSame(0, ProductImage::query()->count());
        $this->assertSame(1, ProductMedia::query()->count());

        $media = ProductMedia::query()->firstOrFail();

        $this->assertSame($response->json('data.id'), $media->id);
        $this->assertSame($response->json('data.media_id'), $media->id);
        $this->assertSame($response->json('data.url'), $media->url);
        $this->assertTrue(Storage::disk('public')->exists($response->json('data.path')));
    }

    public function test_legacy_and_catalog_upload_endpoints_each_create_product_media_only(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->create([
            'name' => 'Dual Surface Product',
            'slug' => 'dual-surface-product',
        ]);

        $this->post('/api/v1/admin/products/'.$product->id.'/images', [
            'image' => MinimalTestImage::jpeg('legacy-first.jpg'),
        ], [
            'Accept' => 'application/json',
        ])->assertCreated();

        $this->post('/api/v1/admin/products/'.$product->id.'/media', [
            'type' => 'image',
            'file' => MinimalTestImage::jpeg('catalog-second.jpg'),
            'alt_text' => 'Catalog second',
        ], [
            'Accept' => 'application/json',
        ])->assertCreated();

        $this->assertSame(0, ProductImage::query()->where('product_id', $product->id)->count());
        $this->assertSame(2, ProductMedia::query()->where('product_id', $product->id)->count());
    }

    public function test_update_product_media_rejects_image_file_replacement(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->create([
            'name' => 'Replace Guard Product',
            'slug' => 'replace-guard-product',
        ]);

        $upload = $this->post('/api/v1/admin/products/'.$product->id.'/media', [
            'type' => 'image',
            'file' => MinimalTestImage::jpeg('original.jpg'),
            'alt_text' => 'Original image',
        ], [
            'Accept' => 'application/json',
        ]);

        $upload->assertCreated();
        $mediaId = $upload->json('data.id');
        $originalUrl = $upload->json('data.url');

        $this->put('/api/v1/admin/products/'.$product->id.'/media/'.$mediaId, [
            'file' => MinimalTestImage::jpeg('replacement.jpg'),
        ], [
            'Accept' => 'application/json',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file'])
            ->assertJsonPath(
                'errors.file.0',
                'Image replacement is not supported. Delete the image and upload a new one.',
            );

        $this->assertSame($originalUrl, ProductMedia::query()->whereKey($mediaId)->value('url'));
        $this->assertSame(0, ProductImage::query()->count());
        $this->assertSame('Original image', ProductMedia::query()->whereKey($mediaId)->value('alt_text'));
    }

    public function test_update_product_media_json_metadata_update_still_works(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->create([
            'name' => 'Metadata Update Product',
            'slug' => 'metadata-update-product',
        ]);

        $upload = $this->post('/api/v1/admin/products/'.$product->id.'/media', [
            'type' => 'image',
            'file' => MinimalTestImage::jpeg('metadata.jpg'),
            'alt_text' => 'Before update',
            'sort_order' => 5,
            'is_active' => true,
        ], [
            'Accept' => 'application/json',
        ]);

        $upload->assertCreated();
        $mediaId = $upload->json('data.id');

        $this->putJson('/api/v1/admin/products/'.$product->id.'/media/'.$mediaId, [
            'alt_text' => 'After update',
            'sort_order' => 1,
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.alt_text', 'After update')
            ->assertJsonPath('data.sort_order', 1)
            ->assertJsonPath('data.is_active', false);

        $media = ProductMedia::query()->whereKey($mediaId)->firstOrFail();
        $this->assertSame('After update', $media->alt_text);
        $this->assertSame(1, $media->sort_order);
        $this->assertFalse($media->is_active);
    }

    public function test_update_product_media_primary_updates_catalog_flags_without_creating_legacy_rows(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->create([
            'name' => 'Primary Sync Product',
            'slug' => 'primary-sync-product',
        ]);

        $first = $this->post('/api/v1/admin/products/'.$product->id.'/media', [
            'type' => 'image',
            'file' => MinimalTestImage::jpeg('first.jpg'),
            'is_primary' => true,
        ], [
            'Accept' => 'application/json',
        ])->assertCreated();

        $second = $this->post('/api/v1/admin/products/'.$product->id.'/media', [
            'type' => 'image',
            'file' => MinimalTestImage::jpeg('second.jpg'),
        ], [
            'Accept' => 'application/json',
        ])->assertCreated();

        $firstMediaId = $first->json('data.id');
        $secondMediaId = $second->json('data.id');

        $this->putJson('/api/v1/admin/products/'.$product->id.'/media/'.$secondMediaId, [
            'is_primary' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_primary', true);

        $this->assertFalse(ProductMedia::query()->whereKey($firstMediaId)->value('is_primary'));
        $this->assertTrue(ProductMedia::query()->whereKey($secondMediaId)->value('is_primary'));
        $this->assertSame(0, ProductImage::query()->count());
        $this->assertSame(2, ProductMedia::query()->where('product_id', $product->id)->count());
    }

    public function test_admin_can_manage_product_catalog_attribute_values(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $department = Department::factory()->create(['slug' => 'spec-dept']);
        $category = Category::factory()->forDepartment($department)->create([
            'name' => 'Phones',
            'slug' => 'spec-phones',
            'parent_id' => null,
        ]);
        $subcategory = Category::factory()->forDepartment($department)->create([
            'name' => 'Smartphones',
            'slug' => 'spec-smartphones',
            'parent_id' => $category->id,
        ]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'name' => 'Android Smartphone',
            'slug' => 'spec-android-smartphone',
        ]);

        $ram = CatalogAttribute::factory()->create([
            'name' => 'RAM',
            'slug' => 'spec-ram',
            'type' => CatalogAttributeType::Select,
            'is_required' => true,
        ]);
        $ram8 = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $ram->id,
            'value' => '8GB',
            'slug' => '8gb',
        ]);
        $ram12 = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $ram->id,
            'value' => '12GB',
            'slug' => '12gb',
        ]);

        $battery = CatalogAttribute::factory()->create([
            'name' => 'Battery Capacity',
            'slug' => 'spec-battery',
            'type' => CatalogAttributeType::Number,
            'unit' => 'mAh',
            'is_required' => true,
        ]);
        $bluetooth = CatalogAttribute::factory()->create([
            'name' => 'Bluetooth',
            'slug' => 'spec-bluetooth',
            'type' => CatalogAttributeType::Boolean,
            'is_required' => false,
        ]);

        $catalogType->attributes()->sync([
            $ram->id => ['is_required' => true, 'sort_order' => 1],
            $battery->id => ['is_required' => true, 'sort_order' => 2],
            $bluetooth->id => ['is_required' => false, 'sort_order' => 3],
        ]);

        $product = Product::factory()->create([
            'name' => 'Spec Galaxy',
            'slug' => 'spec-galaxy',
            'catalog_product_type_id' => $catalogType->id,
            'category_id' => $subcategory->id,
        ]);

        $schema = $this->getJson('/api/v1/admin/products/'.$product->id.'/attributes');
        $schema->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.slug', 'spec-ram')
            ->assertJsonPath('data.0.type', 'select')
            ->assertJsonCount(2, 'data.0.options');

        $this->putJson('/api/v1/admin/products/'.$product->id.'/attributes', [
            'attributes' => [
                ['catalog_attribute_id' => $ram->id, 'option_id' => $ram12->id],
                ['catalog_attribute_id' => $battery->id, 'value_number' => 5000],
                ['catalog_attribute_id' => $bluetooth->id, 'value_boolean' => true],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.0.value.option_id', $ram12->id)
            ->assertJsonPath('data.1.value.value_number', 5000)
            ->assertJsonPath('data.2.value.value_boolean', true);

        $this->assertDatabaseHas('catalog_product_attribute_values', [
            'product_id' => $product->id,
            'catalog_attribute_id' => $ram->id,
            'option_id' => $ram12->id,
        ]);

        $this->putJson('/api/v1/admin/products/'.$product->id.'/attributes', [
            'attributes' => [
                ['catalog_attribute_id' => $ram->id, 'value_number' => 12],
            ],
        ])->assertStatus(422);

        $this->putJson('/api/v1/admin/products/'.$product->id.'/attributes', [
            'attributes' => [
                ['catalog_attribute_id' => $ram->id, 'option_id' => $ram8->id],
                // missing required battery
            ],
        ])->assertStatus(422);

        $this->putJson('/api/v1/admin/products/'.$product->id.'/attributes', [
            'attributes' => [
                ['catalog_attribute_id' => $ram->id, 'option_id' => $ram8->id],
                ['catalog_attribute_id' => $battery->id, 'value_number' => 4500],
                ['catalog_attribute_id' => $bluetooth->id, 'value_boolean' => false],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.0.value.option_id', $ram8->id);

        $this->assertTrue(
            CatalogProductAttributeValue::query()
                ->where('product_id', $product->id)
                ->where('catalog_attribute_id', $battery->id)
                ->where('value_number', 4500)
                ->exists(),
        );
        $this->assertFalse(Schema::hasColumn('products', 'ram'));
    }

    public function test_admin_can_manage_and_generate_product_variants(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $department = Department::factory()->create(['name' => 'Var Dept', 'slug' => 'var-dept']);
        $category = Category::factory()->create([
            'name' => 'Var Phones',
            'slug' => 'var-phones',
            'department_id' => $department->id,
            'parent_id' => null,
        ]);
        $subcategory = Category::factory()->create([
            'name' => 'Var Smartphones',
            'slug' => 'var-smartphones',
            'parent_id' => $category->id,
        ]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'name' => 'Variant Phone Type',
            'slug' => 'variant-phone-type',
        ]);

        $color = CatalogAttribute::factory()->create([
            'name' => 'Color',
            'slug' => 'var-color',
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

        $storage = CatalogAttribute::factory()->create([
            'name' => 'Storage',
            'slug' => 'var-storage',
            'type' => CatalogAttributeType::Select,
        ]);
        $gb128 = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $storage->id,
            'value' => '128GB',
            'slug' => '128gb',
        ]);
        $gb256 = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $storage->id,
            'value' => '256GB',
            'slug' => '256gb',
        ]);

        $catalogType->attributes()->sync([
            $color->id => ['is_required' => false, 'sort_order' => 1],
            $storage->id => ['is_required' => false, 'sort_order' => 2],
        ]);

        $product = Product::factory()->fromChina()->create([
            'name' => 'Variant iPhone',
            'slug' => 'variant-iphone',
            'sku' => 'VAR-IPHONE',
            'catalog_product_type_id' => $catalogType->id,
            'category_id' => $subcategory->id,
        ]);

        $list = $this->getJson('/api/v1/admin/products/'.$product->id.'/variants');
        $list->assertOk()
            ->assertJsonPath('data.variants', [])
            ->assertJsonCount(2, 'data.attributes');

        $manual = $this->postJson('/api/v1/admin/products/'.$product->id.'/variants', [
            'name' => '128GB Black',
            'sku' => 'VAR-IPHONE-128-BLK',
            'status' => 'active',
            'is_default' => true,
            'attribute_values' => [
                ['catalog_attribute_id' => $color->id, 'option_id' => $black->id],
                ['catalog_attribute_id' => $storage->id, 'option_id' => $gb128->id],
            ],
        ]);
        $manual->assertCreated()
            ->assertJsonPath('data.name', '128GB Black')
            ->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.stock', null)
            ->assertJsonCount(2, 'data.attribute_values');

        $variantId = $manual->json('data.id');

        $this->assertDatabaseHas('product_variant_attribute_values', [
            'product_variant_id' => $variantId,
            'catalog_attribute_id' => $color->id,
            'option_id' => $black->id,
        ]);

        $generate = $this->postJson('/api/v1/admin/products/'.$product->id.'/variants/generate', [
            'attributes' => [
                [
                    'catalog_attribute_id' => $color->id,
                    'option_ids' => [$black->id, $white->id],
                ],
                [
                    'catalog_attribute_id' => $storage->id,
                    'option_ids' => [$gb128->id, $gb256->id],
                ],
            ],
            'replace_existing' => false,
        ]);
        $generate->assertOk();
        // 4 combinations minus the existing Black+128GB = 3 new
        $this->assertSame(3, $generate->json('data.created_count'));
        $this->assertCount(4, $generate->json('data.variants'));

        $updated = $this->putJson('/api/v1/admin/products/'.$product->id.'/variants/'.$variantId, [
            'status' => 'inactive',
            'is_default' => false,
        ]);
        $updated->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->deleteJson('/api/v1/admin/products/'.$product->id.'/variants/'.$variantId)
            ->assertOk();

        $this->assertSoftDeleted('product_variants', ['id' => $variantId]);
        $this->assertTrue(
            ProductVariant::query()->where('product_id', $product->id)->where('is_default', true)->exists(),
        );
        $this->assertTrue(Schema::hasTable('product_variant_attribute_values'));
        $this->assertFalse(
            ProductVariantAttributeValue::query()->where('product_variant_id', $variantId)->exists(),
        );
    }

    private function chinaSupplierId(): string
    {
        return Supplier::factory()->create(['is_active' => true, 'country' => 'CN'])->id;
    }
}

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
use App\Models\Department;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttributeValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
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
            'brand_id' => $brand->id,
            'short_description' => 'Product core sample',
            'description' => 'Full description',
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
            ->assertJsonPath('data.alt_text', 'Main shot');

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

        $product = Product::factory()->create([
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
}

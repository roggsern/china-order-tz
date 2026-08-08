<?php

namespace Tests\Feature\Catalog;

use App\Enums\CatalogAttributeType;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use App\Models\CatalogProductAttributeValue;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\Department;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerProductSpecificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_detail_includes_catalog_attribute_specifications(): void
    {
        $department = Department::factory()->create(['slug' => 'pdp-spec-dept']);
        $category = Category::factory()->forDepartment($department)->create([
            'name' => 'Phones',
            'slug' => 'pdp-spec-phones',
            'parent_id' => null,
        ]);
        $subcategory = Category::factory()->forDepartment($department)->create([
            'name' => 'Smartphones',
            'slug' => 'pdp-spec-smartphones',
            'parent_id' => $category->id,
        ]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'name' => 'Android Smartphone',
            'slug' => 'pdp-spec-android-smartphone',
        ]);

        $ram = CatalogAttribute::factory()->create([
            'name' => 'RAM',
            'slug' => 'pdp-spec-ram',
            'type' => CatalogAttributeType::Select,
            'is_required' => true,
        ]);
        $ram12 = CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $ram->id,
            'value' => '12GB',
            'slug' => '12gb',
        ]);

        $battery = CatalogAttribute::factory()->create([
            'name' => 'Battery Capacity',
            'slug' => 'pdp-spec-battery',
            'type' => CatalogAttributeType::Number,
            'unit' => 'mAh',
            'is_required' => true,
        ]);
        $bluetooth = CatalogAttribute::factory()->create([
            'name' => 'Bluetooth',
            'slug' => 'pdp-spec-bluetooth',
            'type' => CatalogAttributeType::Boolean,
            'is_required' => false,
        ]);
        $unsetColor = CatalogAttribute::factory()->create([
            'name' => 'Color',
            'slug' => 'pdp-spec-color',
            'type' => CatalogAttributeType::Text,
            'is_required' => false,
        ]);

        $catalogType->attributes()->sync([
            $ram->id => ['is_required' => true, 'sort_order' => 1],
            $battery->id => ['is_required' => true, 'sort_order' => 2],
            $bluetooth->id => ['is_required' => false, 'sort_order' => 3],
            $unsetColor->id => ['is_required' => false, 'sort_order' => 4],
        ]);

        $product = Product::factory()->create([
            'name' => 'PDP Spec Galaxy',
            'slug' => 'pdp-spec-galaxy',
            'catalog_product_type_id' => $catalogType->id,
            'category_id' => $subcategory->id,
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'is_demo' => false,
            'dimensions' => '10 x 5 x 1 cm',
        ]);

        CatalogProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'catalog_attribute_id' => $ram->id,
            'option_id' => $ram12->id,
            'is_active' => true,
        ]);
        CatalogProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'catalog_attribute_id' => $battery->id,
            'value_number' => 5000,
            'is_active' => true,
        ]);
        CatalogProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'catalog_attribute_id' => $bluetooth->id,
            'value_boolean' => true,
            'is_active' => true,
        ]);

        $this->getJson("/api/v1/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.specifications')
            ->assertJsonPath('data.specifications.0.label', 'RAM')
            ->assertJsonPath('data.specifications.0.value', '12GB')
            ->assertJsonPath('data.specifications.1.label', 'Battery Capacity')
            ->assertJsonPath('data.specifications.1.value', '5000 mAh')
            ->assertJsonPath('data.specifications.2.label', 'Bluetooth')
            ->assertJsonPath('data.specifications.2.value', 'Yes');
    }

    public function test_product_without_catalog_attributes_returns_empty_specifications(): void
    {
        $product = Product::factory()->create([
            'slug' => 'pdp-spec-empty',
            'catalog_product_type_id' => null,
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'is_demo' => false,
        ]);

        $this->getJson("/api/v1/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.specifications', []);
    }

    public function test_specifications_are_scoped_to_product_catalog_type_attributes(): void
    {
        $department = Department::factory()->create(['slug' => 'pdp-spec-type-dept']);
        $phonesCategory = Category::factory()->forDepartment($department)->create([
            'name' => 'Phones',
            'slug' => 'pdp-spec-type-phones',
            'parent_id' => null,
        ]);
        $phoneSub = Category::factory()->forDepartment($department)->create([
            'name' => 'Smartphones',
            'slug' => 'pdp-spec-type-smartphones',
            'parent_id' => $phonesCategory->id,
        ]);
        $camerasCategory = Category::factory()->forDepartment($department)->create([
            'name' => 'Cameras',
            'slug' => 'pdp-spec-type-cameras',
            'parent_id' => null,
        ]);
        $cameraSub = Category::factory()->forDepartment($department)->create([
            'name' => 'Mirrorless',
            'slug' => 'pdp-spec-type-mirrorless',
            'parent_id' => $camerasCategory->id,
        ]);

        $phoneType = CatalogProductType::factory()->create([
            'subcategory_id' => $phoneSub->id,
            'name' => 'Android Smartphone',
            'slug' => 'pdp-spec-type-phone',
        ]);
        $cameraType = CatalogProductType::factory()->create([
            'subcategory_id' => $cameraSub->id,
            'name' => 'Mirrorless Camera',
            'slug' => 'pdp-spec-type-camera',
        ]);

        $ram = CatalogAttribute::factory()->create([
            'name' => 'RAM',
            'slug' => 'pdp-spec-type-ram',
            'type' => CatalogAttributeType::Text,
        ]);
        $sensor = CatalogAttribute::factory()->create([
            'name' => 'Sensor',
            'slug' => 'pdp-spec-type-sensor',
            'type' => CatalogAttributeType::Text,
        ]);

        $phoneType->attributes()->sync([
            $ram->id => ['is_required' => false, 'sort_order' => 1],
        ]);
        $cameraType->attributes()->sync([
            $sensor->id => ['is_required' => false, 'sort_order' => 1],
        ]);

        $phone = Product::factory()->create([
            'name' => 'Type Scoped Phone',
            'slug' => 'pdp-spec-type-phone-product',
            'catalog_product_type_id' => $phoneType->id,
            'category_id' => $phoneSub->id,
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'is_demo' => false,
        ]);
        $camera = Product::factory()->create([
            'name' => 'Type Scoped Camera',
            'slug' => 'pdp-spec-type-camera-product',
            'catalog_product_type_id' => $cameraType->id,
            'category_id' => $cameraSub->id,
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'is_demo' => false,
        ]);

        CatalogProductAttributeValue::factory()->create([
            'product_id' => $phone->id,
            'catalog_attribute_id' => $ram->id,
            'value_text' => '8GB',
            'is_active' => true,
        ]);
        CatalogProductAttributeValue::factory()->create([
            'product_id' => $camera->id,
            'catalog_attribute_id' => $sensor->id,
            'value_text' => 'Full Frame',
            'is_active' => true,
        ]);

        $phoneResponse = $this->getJson("/api/v1/products/{$phone->slug}")
            ->assertOk()
            ->assertJsonCount(1, 'data.specifications')
            ->assertJsonPath('data.specifications.0.label', 'RAM')
            ->assertJsonPath('data.specifications.0.value', '8GB');

        $this->assertSame(
            ['RAM'],
            collect($phoneResponse->json('data.specifications'))->pluck('label')->all(),
        );

        $cameraResponse = $this->getJson("/api/v1/products/{$camera->slug}")
            ->assertOk()
            ->assertJsonCount(1, 'data.specifications')
            ->assertJsonPath('data.specifications.0.label', 'Sensor')
            ->assertJsonPath('data.specifications.0.value', 'Full Frame');

        $this->assertSame(
            ['Sensor'],
            collect($cameraResponse->json('data.specifications'))->pluck('label')->all(),
        );
    }
}

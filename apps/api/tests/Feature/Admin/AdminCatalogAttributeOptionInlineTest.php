<?php

namespace Tests\Feature\Admin;

use App\Enums\CatalogAttributeType;
use App\Models\Admin;
use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\Department;
use App\Models\Product;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCatalogAttributeOptionInlineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_can_create_catalog_attribute_option(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $attribute = CatalogAttribute::factory()->create([
            'name' => 'Color',
            'slug' => 'color-inline',
            'type' => CatalogAttributeType::Select,
        ]);

        $this->postJson('/api/v1/admin/catalog-attributes/'.$attribute->id.'/options', [
            'value' => 'Teal',
            'sort_order' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('data.value', 'Teal')
            ->assertJsonPath('data.catalog_attribute_id', $attribute->id);

        $this->assertDatabaseHas('catalog_attribute_options', [
            'catalog_attribute_id' => $attribute->id,
            'value' => 'Teal',
        ]);
    }

    public function test_rejects_case_insensitive_duplicate_option_values(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $attribute = CatalogAttribute::factory()->create([
            'name' => 'Color',
            'slug' => 'color-dup',
            'type' => CatalogAttributeType::Select,
        ]);
        CatalogAttributeOption::factory()->create([
            'catalog_attribute_id' => $attribute->id,
            'value' => 'Blue',
            'slug' => 'blue',
        ]);

        $this->postJson('/api/v1/admin/catalog-attributes/'.$attribute->id.'/options', [
            'value' => ' blue ',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['value']);

        $this->postJson('/api/v1/admin/catalog-attributes/'.$attribute->id.'/options', [
            'value' => 'BLUE',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['value']);

        $this->assertSame(
            1,
            CatalogAttributeOption::query()->where('catalog_attribute_id', $attribute->id)->count(),
        );
    }

    public function test_product_variants_payload_includes_select_attributes_with_zero_options(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $department = Department::factory()->create(['slug' => 'inline-opt-dept']);
        $category = Category::factory()->create([
            'department_id' => $department->id,
            'parent_id' => null,
            'slug' => 'inline-opt-cat',
        ]);
        $subcategory = Category::factory()->create([
            'department_id' => $department->id,
            'parent_id' => $category->id,
            'slug' => 'inline-opt-sub',
        ]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'slug' => 'inline-opt-type',
        ]);
        $attribute = CatalogAttribute::factory()->create([
            'name' => 'Material',
            'slug' => 'material-empty',
            'type' => CatalogAttributeType::Select,
        ]);
        $catalogType->attributes()->sync([
            $attribute->id => ['is_required' => false, 'sort_order' => 1],
        ]);

        $product = Product::factory()->create([
            'catalog_product_type_id' => $catalogType->id,
            'category_id' => $subcategory->id,
        ]);

        $this->getJson('/api/v1/admin/products/'.$product->id.'/variants')
            ->assertOk()
            ->assertJsonPath('data.attributes.0.catalog_attribute_id', $attribute->id)
            ->assertJsonPath('data.attributes.0.name', 'Material')
            ->assertJsonCount(0, 'data.attributes.0.options');
    }

    public function test_create_option_requires_configuration_manage_permission(): void
    {
        Sanctum::actingAs(
            Admin::factory()->ordinary()->withPermissions([AdminPermissions::CATALOG_UPDATE])->create(),
        );

        $attribute = CatalogAttribute::factory()->create([
            'type' => CatalogAttributeType::Select,
        ]);

        $this->postJson('/api/v1/admin/catalog-attributes/'.$attribute->id.'/options', [
            'value' => 'Denied',
        ])->assertForbidden();
    }
}

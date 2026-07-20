<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Category;
use App\Models\ProductType;
use App\Models\User;
use Database\Seeders\ProductTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductTypeSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_product_types_from_metadata(): void
    {
        $this->seed(ProductTypeSeeder::class);
        Sanctum::actingAs(Admin::factory()->create());

        $response = $this->getJson('/api/v1/admin/product-types');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'name',
                        'slug',
                        'sku_pattern',
                        'has_configurations',
                        'attributes',
                    ],
                ],
            ]);

        $slugs = collect($response->json('data'))->pluck('slug')->all();

        $this->assertContains('fashion', $slugs);
        $this->assertContains('phones', $slugs);
        $this->assertContains('tvs', $slugs);
    }

    public function test_admin_can_load_category_product_form_schema(): void
    {
        $this->seed(ProductTypeSeeder::class);

        $phones = ProductType::query()->where('slug', 'phones')->firstOrFail();
        $category = Category::factory()->create([
            'product_type_id' => $phones->id,
        ]);

        Sanctum::actingAs(Admin::factory()->create());

        $response = $this->getJson("/api/v1/admin/categories/{$category->id}/product-form-schema");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product_type.slug', 'phones')
            ->assertJsonPath('data.capabilities.has_configurations', true);

        $attributeSlugs = collect($response->json('data.attributes'))->pluck('slug')->all();

        $this->assertContains('storage', $attributeSlugs);
        $this->assertContains('color', $attributeSlugs);
        $this->assertNotEmpty($response->json('data.dependencies'));
    }

    public function test_schema_inherits_product_type_from_parent_category(): void
    {
        $this->seed(ProductTypeSeeder::class);

        $fashion = ProductType::query()->where('slug', 'fashion')->firstOrFail();
        $parent = Category::factory()->create([
            'product_type_id' => $fashion->id,
        ]);
        $child = Category::factory()->create([
            'parent_id' => $parent->id,
            'product_type_id' => null,
        ]);

        Sanctum::actingAs(Admin::factory()->create());

        $this->getJson("/api/v1/admin/categories/{$child->id}/product-form-schema")
            ->assertOk()
            ->assertJsonPath('data.product_type.slug', 'fashion');
    }

    public function test_schema_has_no_hardcoded_product_logic_keys(): void
    {
        $this->seed(ProductTypeSeeder::class);

        $phones = ProductType::query()->where('slug', 'phones')->firstOrFail();
        $category = Category::factory()->create(['product_type_id' => $phones->id]);

        Sanctum::actingAs(Admin::factory()->create());

        $payload = $this->getJson("/api/v1/admin/categories/{$category->id}/product-form-schema")
            ->assertOk()
            ->json('data');

        // Consumers must use attributes[] from metadata — no phone-specific fields.
        $this->assertArrayNotHasKey('storage', $payload);
        $this->assertArrayNotHasKey('screen_size', $payload);
        $this->assertArrayHasKey('attributes', $payload);
        $this->assertArrayHasKey('dependencies', $payload);
    }

    public function test_guest_cannot_list_product_types(): void
    {
        $this->getJson('/api/v1/admin/product-types')->assertUnauthorized();
    }

    public function test_customer_cannot_list_product_types(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/product-types')->assertUnauthorized();
    }
}

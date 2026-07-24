<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductLifecycleStatus;
use App\Models\Admin;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Department;
use App\Models\Product;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductCreateAsActivePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_only_admin_cannot_create_active_product(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::CATALOG_CREATE])->create(),
        );

        $response = $this->postJson('/api/v1/admin/products', $this->publishableCreatePayload([
            'name' => 'Forced Draft From Active Request',
            'lifecycle_status' => ProductLifecycleStatus::Active->value,
        ]));

        $response->assertCreated()->assertJsonPath('success', true);

        $product = Product::query()->where('name', 'Forced Draft From Active Request')->first();
        $this->assertNotNull($product);
        $this->assertSame(ProductLifecycleStatus::Draft, $product->lifecycle_status);
        $this->assertFalse($product->is_active);
    }

    public function test_create_only_admin_cannot_create_active_via_legacy_status_boolean(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::CATALOG_CREATE])->create(),
        );

        $response = $this->postJson('/api/v1/admin/products', $this->publishableCreatePayload([
            'name' => 'Forced Draft From Legacy Status',
            'status' => true,
        ]));

        $response->assertCreated();

        $product = Product::query()->where('name', 'Forced Draft From Legacy Status')->first();
        $this->assertNotNull($product);
        $this->assertSame(ProductLifecycleStatus::Draft, $product->lifecycle_status);
        $this->assertFalse($product->is_active);
    }

    public function test_create_only_admin_creates_draft_successfully(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::CATALOG_CREATE])->create(),
        );

        $response = $this->postJson('/api/v1/admin/products', $this->publishableCreatePayload([
            'name' => 'Explicit Draft Product',
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
        ]));

        $response->assertCreated();

        $product = Product::query()->where('name', 'Explicit Draft Product')->first();
        $this->assertNotNull($product);
        $this->assertSame(ProductLifecycleStatus::Draft, $product->lifecycle_status);
        $this->assertFalse($product->is_active);
    }

    public function test_create_and_publish_admin_can_create_active_product(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::CATALOG_CREATE,
                AdminPermissions::CATALOG_PUBLISH,
            ])->create(),
        );

        $response = $this->postJson('/api/v1/admin/products', $this->publishableCreatePayload([
            'name' => 'Published On Create',
            'lifecycle_status' => ProductLifecycleStatus::Active->value,
        ]));

        $response->assertCreated();

        $product = Product::query()->where('name', 'Published On Create')->first();
        $this->assertNotNull($product);
        $this->assertSame(ProductLifecycleStatus::Active, $product->lifecycle_status);
        $this->assertTrue($product->is_active);
    }

    public function test_create_as_active_still_runs_publish_validation(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::CATALOG_CREATE,
                AdminPermissions::CATALOG_PUBLISH,
            ])->create(),
        );

        $payload = $this->publishableCreatePayload([
            'name' => 'Active Without Shipping',
            'lifecycle_status' => ProductLifecycleStatus::Active->value,
        ]);
        unset($payload['shipping_options']);

        $this->postJson('/api/v1/admin/products', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_options']);

        $this->assertNull(Product::query()->where('name', 'Active Without Shipping')->value('id'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function publishableCreatePayload(array $overrides = []): array
    {
        $department = Department::factory()->create();
        $category = Category::factory()->forDepartment($department)->create(['parent_id' => null]);
        $subcategory = Category::factory()->forDepartment($department)->create([
            'parent_id' => $category->id,
        ]);
        $catalogProductType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'is_active' => true,
        ]);

        $chinaChannelId = CommerceChannel::query()->where('code', 'CHINA_IMPORT')->value('id');

        return array_merge([
            'name' => 'Create Permission Test Product',
            'category_id' => $subcategory->id,
            'catalog_product_type_id' => $catalogProductType->id,
            'commerce_channel_id' => $chinaChannelId,
            'price' => 50000,
            'stock_quantity' => 5,
            'shipping_options' => [
                [
                    'transport_mode' => 'air',
                    'price' => 5000,
                    'currency' => 'TZS',
                    'is_available' => true,
                ],
            ],
        ], $overrides);
    }
}

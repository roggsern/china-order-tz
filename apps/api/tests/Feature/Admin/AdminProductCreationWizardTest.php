<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductLifecycleStatus;
use App\Models\Admin;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Department;
use App\Models\Product;
use App\Models\Store;
use App\Models\Supplier;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductCreationWizardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *     catalogType: CatalogProductType,
     *     chinaChannelId: string,
     *     tzChannelId: string,
     *     store: Store,
     *     supplier: Supplier
     * }
     */
    private function catalogFixture(): array
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

        return [
            'catalogType' => $catalogType,
            'chinaChannelId' => CommerceChannel::query()->where('code', 'CHINA_IMPORT')->value('id'),
            'tzChannelId' => CommerceChannel::query()->where('code', 'TZ_LOCAL')->value('id'),
            'store' => Store::query()->create([
                'code' => 'WIZ1',
                'name' => 'Wizard Store',
                'slug' => 'wizard-store',
                'is_active' => true,
            ]),
            'supplier' => Supplier::factory()->create(['is_active' => true, 'country' => 'CN']),
        ];
    }

    public function test_create_china_draft_without_supplier_succeeds_for_wizard_flow(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::CATALOG_CREATE])->create(),
        );

        ['catalogType' => $catalogType, 'chinaChannelId' => $chinaChannelId] = $this->catalogFixture();

        $this->postJson('/api/v1/admin/products', [
            'name' => 'Wizard Draft China',
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => $chinaChannelId,
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
            'price' => 0,
        ])
            ->assertCreated()
            ->assertJsonPath('data.lifecycle_status', ProductLifecycleStatus::Draft->value);

        $this->assertDatabaseHas('products', [
            'name' => 'Wizard Draft China',
            'supplier_id' => null,
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
        ]);
    }

    public function test_create_tz_draft_without_store_succeeds_for_wizard_flow(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::CATALOG_CREATE])->create(),
        );

        ['catalogType' => $catalogType, 'tzChannelId' => $tzChannelId] = $this->catalogFixture();

        $this->postJson('/api/v1/admin/products', [
            'name' => 'Wizard Draft TZ',
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => $tzChannelId,
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
            'price' => 0,
        ])
            ->assertCreated()
            ->assertJsonPath('data.lifecycle_status', ProductLifecycleStatus::Draft->value);

        $product = Product::query()->where('name', 'Wizard Draft TZ')->first();
        $this->assertNotNull($product);
        $this->assertNull($product->store_id);
    }

    public function test_update_draft_step_fields_without_store_or_supplier_succeeds(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::CATALOG_CREATE, AdminPermissions::CATALOG_UPDATE])->create(),
        );

        ['catalogType' => $catalogType, 'chinaChannelId' => $chinaChannelId] = $this->catalogFixture();

        $create = $this->postJson('/api/v1/admin/products', [
            'name' => 'Step Update Draft',
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => $chinaChannelId,
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
        ])->assertCreated();

        $productId = $create->json('data.id');

        $this->putJson('/api/v1/admin/products/'.$productId, [
            'description' => 'Updated in wizard media/pricing step',
            'price' => 25000,
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Updated in wizard media/pricing step');
    }

    public function test_publish_active_china_draft_still_requires_supplier(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::CATALOG_CREATE,
                AdminPermissions::CATALOG_UPDATE,
                AdminPermissions::CATALOG_PUBLISH,
            ])->create(),
        );

        ['catalogType' => $catalogType, 'chinaChannelId' => $chinaChannelId] = $this->catalogFixture();

        $create = $this->postJson('/api/v1/admin/products', [
            'name' => 'Publish Guard China',
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => $chinaChannelId,
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
        ])->assertCreated();

        $productId = $create->json('data.id');

        $this->putJson('/api/v1/admin/products/'.$productId, [
            'lifecycle_status' => ProductLifecycleStatus::Active->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['supplier_id']);
    }

    public function test_publish_active_tz_draft_still_requires_store(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::CATALOG_CREATE,
                AdminPermissions::CATALOG_UPDATE,
                AdminPermissions::CATALOG_PUBLISH,
            ])->create(),
        );

        ['catalogType' => $catalogType, 'tzChannelId' => $tzChannelId] = $this->catalogFixture();

        $create = $this->postJson('/api/v1/admin/products', [
            'name' => 'Publish Guard TZ',
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => $tzChannelId,
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
        ])->assertCreated();

        $productId = $create->json('data.id');

        $this->putJson('/api/v1/admin/products/'.$productId, [
            'lifecycle_status' => ProductLifecycleStatus::Active->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['store_id']);
    }
}

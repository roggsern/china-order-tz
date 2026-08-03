<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductPricingModel;
use App\Models\Admin;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Department;
use App\Models\Product;
use App\Models\Store;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductPricingModelPersistenceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{catalogType: CatalogProductType, chinaChannelId: string, tzChannelId: string, store: Store}
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

        $store = Store::query()->create([
            'code' => 'PM01',
            'name' => 'Pricing Model Store',
            'slug' => 'pricing-model-store',
            'is_active' => true,
        ]);

        return [
            'catalogType' => $catalogType,
            'chinaChannelId' => CommerceChannel::query()->where('code', 'CHINA_IMPORT')->value('id'),
            'tzChannelId' => CommerceChannel::query()->where('code', 'TZ_LOCAL')->value('id'),
            'store' => $store,
        ];
    }

    public function test_create_variant_intent_draft_persists_pricing_model(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        ['catalogType' => $catalogType, 'chinaChannelId' => $chinaChannelId] = $this->catalogFixture();

        $create = $this->postJson('/api/v1/admin/products', [
            'name' => 'Variant Intent Draft',
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => $chinaChannelId,
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
            'price' => 0,
            'pricing_model' => ProductPricingModel::Variant->value,
        ])->assertCreated();

        $productId = $create->json('data.id');
        $create->assertJsonPath('data.pricing_model', ProductPricingModel::Variant->value);

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'pricing_model' => ProductPricingModel::Variant->value,
            'price' => 0,
        ]);

        $this->getJson('/api/v1/admin/products/'.$productId)
            ->assertOk()
            ->assertJsonPath('data.pricing_model', ProductPricingModel::Variant->value);
    }

    public function test_create_simple_intent_draft_persists_pricing_model(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        ['tzChannelId' => $tzChannelId, 'store' => $store] = $this->catalogFixture();
        $root = Category::factory()->forStore($store)->create(['parent_id' => null]);
        $subcategory = Category::factory()->forStore($store)->child($root)->create();
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
            'is_active' => true,
        ]);

        $create = $this->postJson('/api/v1/admin/products', [
            'name' => 'Simple Intent Draft',
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => $tzChannelId,
            'store_id' => $store->id,
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
            'price' => 12000,
            'pricing_model' => ProductPricingModel::Simple->value,
        ])->assertCreated();

        $productId = $create->json('data.id');
        $create->assertJsonPath('data.pricing_model', ProductPricingModel::Simple->value);

        $this->getJson('/api/v1/admin/products/'.$productId)
            ->assertOk()
            ->assertJsonPath('data.pricing_model', ProductPricingModel::Simple->value)
            ->assertJsonPath('data.price', '12000.00');
    }

    public function test_create_without_pricing_model_defaults_to_simple(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::CATALOG_CREATE])->create(),
        );

        ['catalogType' => $catalogType, 'chinaChannelId' => $chinaChannelId] = $this->catalogFixture();

        $this->postJson('/api/v1/admin/products', [
            'name' => 'Legacy Default Draft',
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => $chinaChannelId,
            'lifecycle_status' => ProductLifecycleStatus::Draft->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.pricing_model', ProductPricingModel::Simple->value);
    }

    public function test_existing_active_product_exposes_simple_pricing_model_default(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $product = Product::factory()->fromChina()->create([
            'name' => 'Existing Active Product',
            'slug' => 'existing-active-product',
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
            'price' => 99000,
        ]);

        $this->getJson('/api/v1/admin/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.pricing_model', ProductPricingModel::Simple->value)
            ->assertJsonPath('data.price', '99000.00')
            ->assertJsonPath('data.lifecycle_status', ProductLifecycleStatus::Active->value);
    }
}

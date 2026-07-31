<?php

namespace Tests\Feature\Admin;

use App\Enums\ChinaWorkflowStage;
use App\Enums\FulfillmentStrategy;
use App\Enums\OrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\Admin;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Department;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\China\ChinaWorkflowEngine;
use App\Services\Fulfillment\FulfillmentEngine;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductSupplierAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Sanctum::actingAs(Admin::factory()->create());
    }

    /**
     * @return array{
     *     catalogType: CatalogProductType,
     *     chinaChannelId: string,
     *     supplier: Supplier
     * }
     */
    private function chinaCatalogFixture(): array
    {
        $department = Department::factory()->create();
        $category = Category::factory()->forDepartment($department)->create(['parent_id' => null]);
        $subcategory = Category::factory()->forDepartment($department)->create([
            'parent_id' => $category->id,
        ]);
        $catalogType = CatalogProductType::factory()->create([
            'subcategory_id' => $subcategory->id,
        ]);
        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $chinaChannelId = CommerceChannel::query()->where('code', 'CHINA_IMPORT')->value('id');

        return compact('catalogType', 'chinaChannelId', 'supplier');
    }

    public function test_create_china_product_with_supplier_succeeds(): void
    {
        ['catalogType' => $catalogType, 'chinaChannelId' => $chinaChannelId, 'supplier' => $supplier] = $this->chinaCatalogFixture();

        $response = $this->postJson('/api/v1/admin/products', [
            'name' => 'China Widget',
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => $chinaChannelId,
            'supplier_id' => $supplier->id,
            'price' => 120000,
            'stock_quantity' => 5,
            'status' => 'draft',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.supplier_id', $supplier->id);

        $this->assertDatabaseHas('products', [
            'name' => 'China Widget',
            'supplier_id' => $supplier->id,
        ]);
    }

    public function test_create_china_product_without_supplier_fails_validation_when_active(): void
    {
        ['catalogType' => $catalogType, 'chinaChannelId' => $chinaChannelId] = $this->chinaCatalogFixture();

        $this->postJson('/api/v1/admin/products', [
            'name' => 'China Widget Missing Supplier',
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => $chinaChannelId,
            'price' => 120000,
            'stock_quantity' => 5,
            'lifecycle_status' => 'active',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['supplier_id']);
    }

    public function test_create_china_draft_without_supplier_succeeds(): void
    {
        ['catalogType' => $catalogType, 'chinaChannelId' => $chinaChannelId] = $this->chinaCatalogFixture();

        $this->postJson('/api/v1/admin/products', [
            'name' => 'China Draft Without Supplier',
            'catalog_product_type_id' => $catalogType->id,
            'commerce_channel_id' => $chinaChannelId,
            'price' => 120000,
            'status' => 'draft',
        ])
            ->assertCreated()
            ->assertJsonPath('data.supplier_id', null);
    }

    public function test_existing_china_product_can_assign_supplier_on_update(): void
    {
        ['catalogType' => $catalogType, 'chinaChannelId' => $chinaChannelId, 'supplier' => $supplier] = $this->chinaCatalogFixture();

        $product = Product::factory()->create([
            'catalog_product_type_id' => $catalogType->id,
            'category_id' => $catalogType->subcategory_id,
            'commerce_channel_id' => $chinaChannelId,
            'fulfillment_source' => 'imported_from_china',
            'supplier_id' => null,
        ]);

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'supplier_id' => $supplier->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.supplier_id', $supplier->id);

        $this->assertSame($supplier->id, $product->fresh()->supplier_id);
    }

    public function test_china_product_with_supplier_bootstraps_supplier_purchase(): void
    {
        ['catalogType' => $catalogType, 'chinaChannelId' => $chinaChannelId, 'supplier' => $supplier] = $this->chinaCatalogFixture();

        $product = Product::factory()->create([
            'catalog_product_type_id' => $catalogType->id,
            'category_id' => $catalogType->subcategory_id,
            'commerce_channel_id' => $chinaChannelId,
            'fulfillment_source' => 'imported_from_china',
            'supplier_id' => $supplier->id,
            'cost_price' => 90000,
        ]);

        Inventory::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => 10,
            'reserved_quantity' => 0,
        ]);

        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => 1,
            'unit_price' => 150000,
        ]);

        $fulfillment = app(FulfillmentEngine::class)->createForOrder($order->fresh(['items.product.supplier']));
        $this->assertSame(FulfillmentStrategy::China, $fulfillment->strategy);

        $record = app(ChinaWorkflowEngine::class)->bootstrapFromFulfillment($fulfillment);

        $this->assertSame(ChinaWorkflowStage::ProcurementInProgress, $record->stage);
        $this->assertDatabaseHas('purchase_orders', [
            'order_id' => $order->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Draft->value,
        ]);
        $this->assertSame(1, PurchaseOrder::query()->where('order_id', $order->id)->count());
    }
}

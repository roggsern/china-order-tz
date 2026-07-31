<?php

namespace Tests\Feature\Warehouse;

use App\Enums\ActivityEventType;
use App\Enums\NotificationEventType;
use App\Enums\OrderStatus;
use App\Enums\WarehouseJobStatus;
use App\Enums\WarehousePickListStatus;
use App\Enums\WarehouseStockTransferStatus;
use App\Models\Admin;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantInventory;
use App\Models\WarehouseFacility;
use App\Models\WarehouseJob;
use App\Models\WarehousePickList;
use App\Models\WarehouseStockTransfer;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminWarehouseOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NotificationTemplateSeeder::class);
    }

    private function paidJobWithItems(): array
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['fulfillment_source' => 'imported_from_china']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 5,
            'unit_price' => 10000,
            'total_price' => 50000,
            'line_total' => 50000,
        ]);

        $fulfillment = app(\App\Services\Fulfillment\FulfillmentEngine::class)
            ->createForOrder($order->fresh(['items.product.supplier']));
        $job = $fulfillment->fresh('warehouseJob')->warehouseJob;
        $this->assertNotNull($job);

        return compact('user', 'order', 'variant', 'job');
    }

    public function test_pick_list_creation_quantity_validation_and_completion(): void
    {
        ['user' => $user, 'job' => $job] = $this->paidJobWithItems();

        $admin = Admin::factory()->withPermissions([
            AdminPermissions::WAREHOUSE_VIEW,
            AdminPermissions::WAREHOUSE_MANAGE,
        ])->create();
        Sanctum::actingAs($admin);

        $pickListId = $this->postJson('/api/v1/admin/warehouse/pick-lists', [
            'warehouse_job_id' => $job->id,
        ])->assertCreated()
            ->assertJsonPath('data.status', WarehousePickListStatus::Pending->value)
            ->json('data.id');

        $this->assertDatabaseHas('warehouse_pick_list_lines', [
            'pick_list_id' => $pickListId,
            'quantity' => 5,
            'picked_quantity' => 0,
        ]);

        $lineId = \App\Models\WarehousePickListLine::query()
            ->where('pick_list_id', $pickListId)
            ->value('id');

        $this->patchJson("/api/v1/admin/warehouse/pick-lists/{$pickListId}/lines/{$lineId}", [
            'picked_quantity' => 6,
        ])->assertStatus(422);

        $this->postJson("/api/v1/admin/warehouse/pick-lists/{$pickListId}/start")->assertOk();
        $this->assertDatabaseHas('activity_logs', ['event_type' => ActivityEventType::PickStarted->value]);

        $this->patchJson("/api/v1/admin/warehouse/pick-lists/{$pickListId}/lines/{$lineId}", [
            'picked_quantity' => 5,
        ])->assertOk();

        $this->postJson("/api/v1/admin/warehouse/pick-lists/{$pickListId}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', WarehousePickListStatus::Completed->value);

        $this->assertSame(WarehouseJobStatus::Picked, WarehouseJob::query()->find($job->id)?->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['event_type' => ActivityEventType::PickCompleted->value]);
        $this->assertDatabaseHas('notifications', [
            'customer_id' => $user->id,
            'event_type' => NotificationEventType::WarehousePickCompleted->value,
        ]);
    }

    public function test_transfer_permissions_and_inventory_movement(): void
    {
        ['variant' => $variant] = $this->paidJobWithItems();

        $from = WarehouseFacility::query()->create([
            'code' => 'WH-A',
            'name' => 'Warehouse A',
            'inventory_warehouse_code' => 'MAIN',
        ]);
        $to = WarehouseFacility::query()->create([
            'code' => 'WH-B',
            'name' => 'Warehouse B',
            'inventory_warehouse_code' => 'CHINA',
        ]);

        VariantInventory::factory()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 20,
        ]);

        Sanctum::actingAs(Admin::factory()->withoutPermissions()->create());
        $this->getJson('/api/v1/admin/warehouse/transfers')->assertForbidden();

        $manager = Admin::factory()->withPermissions([
            AdminPermissions::WAREHOUSE_TRANSFER,
        ])->create();
        Sanctum::actingAs($manager);

        $transferId = $this->postJson('/api/v1/admin/warehouse/transfers', [
            'from_facility_id' => $from->id,
            'to_facility_id' => $to->id,
            'lines' => [['product_variant_id' => $variant->id, 'quantity' => 4]],
        ])->assertCreated()
            ->assertJsonPath('data.status', WarehouseStockTransferStatus::Requested->value)
            ->json('data.id');

        $this->assertDatabaseHas('activity_logs', ['event_type' => ActivityEventType::TransferCreated->value]);

        $this->postJson("/api/v1/admin/warehouse/transfers/{$transferId}/approve")->assertOk();
        $this->postJson("/api/v1/admin/warehouse/transfers/{$transferId}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', WarehouseStockTransferStatus::Transferred->value);

        $this->assertDatabaseHas('activity_logs', ['event_type' => ActivityEventType::TransferCompleted->value]);
        $this->assertDatabaseHas('inventory_stock_movements', [
            'reference_type' => WarehouseStockTransfer::class,
            'reference_id' => $transferId,
        ]);
    }

    public function test_viewer_can_list_pick_lists_but_not_update(): void
    {
        ['job' => $job] = $this->paidJobWithItems();

        Sanctum::actingAs(Admin::factory()->withPermissions([
            AdminPermissions::WAREHOUSE_MANAGE,
        ])->create());

        $pickListId = $this->postJson('/api/v1/admin/warehouse/pick-lists', [
            'warehouse_job_id' => $job->id,
        ])->json('data.id');

        Sanctum::actingAs(Admin::factory()->withPermissions([
            AdminPermissions::WAREHOUSE_VIEW,
        ])->create());

        $this->getJson('/api/v1/admin/warehouse/pick-lists')->assertOk();
        $this->postJson("/api/v1/admin/warehouse/pick-lists/{$pickListId}/start")->assertForbidden();
    }
}

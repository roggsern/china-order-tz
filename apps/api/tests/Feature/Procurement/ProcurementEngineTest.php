<?php

namespace Tests\Feature\Procurement;

use App\Enums\ActivityEventType;
use App\Enums\NotificationEventType;
use App\Enums\PurchaseOrderStatus;
use App\Models\Admin;
use App\Models\InventoryLog;
use App\Models\Notification;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\SupplierCostHistory;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Models\VariantInventory;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProcurementEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_crud_and_product_mapping(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $created = $this->postJson('/api/v1/admin/suppliers', [
            'name' => 'Dar Local Supply',
            'code' => 'DAR_LOCAL_01',
            'country' => 'Tanzania',
            'contact_person' => 'Amina Juma',
            'payment_terms' => 'Net 15',
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'DAR_LOCAL_01')
            ->assertJsonPath('data.country', 'Tanzania');

        $supplierId = $created->json('data.id');

        $this->getJson('/api/v1/admin/suppliers')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->putJson("/api/v1/admin/suppliers/{$supplierId}", [
            'notes' => 'Preferred local supplier',
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('data.notes', 'Preferred local supplier');

        ['variant' => $variant] = CatalogCartFixture::purchasable(15000);

        $this->postJson("/api/v1/admin/suppliers/{$supplierId}/products", [
            'product_variant_id' => $variant->id,
            'supplier_sku' => 'LOC-KETTLE-01',
            'purchase_cost' => 9000,
            'currency' => 'TZS',
            'lead_time_days' => 5,
        ])->assertCreated()
            ->assertJsonPath('data.supplier_sku', 'LOC-KETTLE-01')
            ->assertJsonPath('data.purchase_cost', '9000.00');

        $this->assertDatabaseHas('supplier_products', [
            'supplier_id' => $supplierId,
            'product_variant_id' => $variant->id,
            'supplier_sku' => 'LOC-KETTLE-01',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::SupplierCreated->value,
            'subject_id' => $supplierId,
        ]);
    }

    public function test_purchase_order_lifecycle_receiving_and_inventory(): void
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        Sanctum::actingAs($admin);

        $supplier = Supplier::factory()->create(['country' => 'China', 'is_active' => true]);
        ['variant' => $variant] = CatalogCartFixture::purchasable(20000);

        VariantInventory::withTrashed()
            ->where('product_variant_id', $variant->id)
            ->forceDelete();

        $poResponse = $this->postJson('/api/v1/admin/purchase-orders', [
            'supplier_id' => $supplier->id,
            'currency' => 'TZS',
            'notes' => 'Import batch',
            'items' => [
                [
                    'product_variant_id' => $variant->id,
                    'quantity_ordered' => 10,
                    'unit_cost' => 12000,
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        $poId = $poResponse->json('data.id');
        $itemId = $poResponse->json('data.items.0.id');

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::PurchaseOrderCreated->value,
            'subject_id' => $poId,
        ]);

        // Cannot receive while draft.
        $this->postJson("/api/v1/admin/purchase-orders/{$poId}/receive", [
            'items' => [['purchase_order_item_id' => $itemId, 'quantity' => 4]],
        ])->assertStatus(422);

        $this->patchJson("/api/v1/admin/purchase-orders/{$poId}/status", ['status' => 'sent'])
            ->assertOk()
            ->assertJsonPath('data.status', 'sent');

        $this->patchJson("/api/v1/admin/purchase-orders/{$poId}/status", ['status' => 'confirmed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::PurchaseOrderConfirmed->value,
            'subject_id' => $poId,
        ]);

        $this->assertTrue(
            Notification::query()
                ->where('event_type', NotificationEventType::PurchaseOrderConfirmed->value)
                ->where('admin_id', $admin->id)
                ->exists()
        );

        // Exceed ordered quantity rejected.
        $this->postJson("/api/v1/admin/purchase-orders/{$poId}/receive", [
            'items' => [['purchase_order_item_id' => $itemId, 'quantity' => 11]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);

        $this->postJson("/api/v1/admin/purchase-orders/{$poId}/receive", [
            'items' => [['purchase_order_item_id' => $itemId, 'quantity' => 4]],
            'notes' => 'First shipment',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('purchase_order.status', 'partially_received');

        $inventory = VariantInventory::query()
            ->where('product_variant_id', $variant->id)
            ->where('warehouse_code', 'MAIN')
            ->first();

        $this->assertNotNull($inventory);
        $this->assertSame(4, (int) $inventory->on_hand);

        $this->assertDatabaseHas('inventory_logs', [
            'product_variant_id' => $variant->id,
            'quantity_change' => 4,
            'reason' => 'procurement_receipt',
        ]);

        $this->assertDatabaseHas('supplier_cost_histories', [
            'supplier_id' => $supplier->id,
            'product_variant_id' => $variant->id,
            'purchase_cost' => 12000,
        ]);

        $this->assertTrue(
            Notification::query()
                ->where('event_type', NotificationEventType::GoodsReceived->value)
                ->exists()
        );

        $this->postJson("/api/v1/admin/purchase-orders/{$poId}/receive", [
            'items' => [['purchase_order_item_id' => $itemId, 'quantity' => 6]],
        ])->assertCreated()
            ->assertJsonPath('purchase_order.status', 'completed');

        $inventory->refresh();
        $this->assertSame(10, (int) $inventory->on_hand);

        $this->assertSame(
            10,
            (int) PurchaseOrderItem::query()->whereKey($itemId)->value('quantity_received')
        );

        // PO does not mutate inventory before receive — already verified draft receive blocked.
        $this->assertSame(PurchaseOrderStatus::Completed, PurchaseOrder::query()->findOrFail($poId)->status);
    }

    public function test_authorization_for_procurement_apis(): void
    {
        $this->getJson('/api/v1/admin/suppliers')->assertUnauthorized();
        $this->getJson('/api/v1/admin/purchase-orders')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/suppliers')->assertUnauthorized();
        $this->postJson('/api/v1/admin/purchase-orders', [])->assertUnauthorized();
    }

    public function test_purchase_order_does_not_change_inventory_on_create(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $supplier = Supplier::factory()->create(['is_active' => true]);
        ['variant' => $variant] = CatalogCartFixture::purchasable(10000);

        $before = (int) (VariantInventory::query()
            ->where('product_variant_id', $variant->id)
            ->value('on_hand') ?? 0);

        $this->postJson('/api/v1/admin/purchase-orders', [
            'supplier_id' => $supplier->id,
            'items' => [[
                'product_variant_id' => $variant->id,
                'quantity_ordered' => 5,
                'unit_cost' => 1000,
            ]],
        ])->assertCreated();

        $after = (int) (VariantInventory::query()
            ->where('product_variant_id', $variant->id)
            ->value('on_hand') ?? 0);

        $this->assertSame($before, $after);
        $this->assertSame(0, InventoryLog::query()->where('reason', 'procurement_receipt')->count());
        $this->assertSame(0, SupplierProduct::query()->count());
        $this->assertSame(0, SupplierCostHistory::query()->count());
    }
}

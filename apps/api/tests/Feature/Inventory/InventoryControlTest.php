<?php

namespace Tests\Feature\Inventory;

use App\Enums\ActivityEventType;
use App\Enums\CommerceChannelCode;
use App\Enums\InventoryCountStatus;
use App\Enums\InventoryMovementType;
use App\Enums\PosPaymentHandler;
use App\Enums\PurchaseOrderStatus;
use App\Models\Admin;
use App\Models\CommerceChannel;
use App\Models\InventoryCountSession;
use App\Models\InventoryStockMovement;
use App\Models\PaymentMethodDefinition;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use App\Enums\VariantPriceType;
use App\Services\Inventory\InventoryControlEngine;
use App\Services\Stores\StoreAssignmentService;
use App\Services\Stores\StoreService;
use Database\Factories\Support\CatalogCartFixture;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryControlTest extends TestCase
{
    use RefreshDatabase;

    private StoreService $stores;

    private StoreAssignmentService $assignments;

    private CommerceChannel $tz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->stores = app(StoreService::class);
        $this->assignments = app(StoreAssignmentService::class);
        $this->tz = CommerceChannel::query()->updateOrCreate(
            ['code' => CommerceChannelCode::TzLocal->value],
            ['name' => 'Buy From TZ', 'is_active' => true],
        );

        PaymentMethodDefinition::query()->updateOrCreate(
            ['code' => 'CASH'],
            [
                'name' => 'Cash',
                'is_active' => true,
                'sort_order' => 1,
                'config' => ['handler' => PosPaymentHandler::CashWithChange->value, 'pos_enabled' => true],
            ],
        );
    }

    public function test_receiving_increases_inventory_and_cannot_exceed_po_quantity(): void
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        Sanctum::actingAs($admin);

        $store = $this->stores->create(['code' => 'ZION', 'name' => 'Zion Mode']);
        $supplier = Supplier::factory()->create(['is_active' => true]);
        ['variant' => $variant] = CatalogCartFixture::purchasable(20000);
        $variant->product->forceFill(['store_id' => $store->id])->save();

        VariantInventory::withTrashed()->where('product_variant_id', $variant->id)->forceDelete();

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Confirmed,
        ]);
        $item = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_variant_id' => $variant->id,
            'quantity_ordered' => 10,
            'quantity_received' => 0,
            'unit_cost' => 5000,
        ]);

        $this->postJson("/api/v1/admin/purchase-orders/{$po->id}/receive", [
            'store_id' => $store->id,
            'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 11]],
        ])->assertStatus(422);

        $this->postJson("/api/v1/admin/purchase-orders/{$po->id}/receive", [
            'store_id' => $store->id,
            'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 4]],
            'notes' => 'Partial receive',
        ])->assertCreated();

        $location = $this->stores->defaultLocation($store);
        $inventory = VariantInventory::query()
            ->where('product_variant_id', $variant->id)
            ->where('inventory_location_id', $location->id)
            ->first();

        $this->assertNotNull($inventory);
        $this->assertSame(4, (int) $inventory->on_hand);
        $this->assertDatabaseHas('inventory_stock_movements', [
            'product_variant_id' => $variant->id,
            'movement_type' => InventoryMovementType::Receive->value,
            'quantity_change' => 4,
            'store_id' => $store->id,
        ]);
    }

    public function test_adjustment_requires_reason_and_writes_ledger(): void
    {
        $admin = Admin::factory()->superAdmin()->create(['is_active' => true]);
        Sanctum::actingAs($admin);
        $store = $this->stores->create(['code' => 'ROVI', 'name' => 'Rovi Beauty']);
        $sku = $this->seedSku($store, 10000, 20);

        $this->postJson('/api/v1/admin/inventory/adjustments', [
            'store_id' => $store->id,
            'product_variant_id' => $sku['variant_id'],
            'quantity_change' => -2,
            'reason' => '',
        ])->assertStatus(422);

        $this->postJson('/api/v1/admin/inventory/adjustments', [
            'store_id' => $store->id,
            'product_variant_id' => $sku['variant_id'],
            'quantity_change' => -2,
            'reason' => 'Damaged in display',
            'kind' => 'damage',
        ])->assertCreated();

        $inv = VariantInventory::query()->findOrFail($sku['inventory_id']);
        $this->assertSame(18, (int) $inv->on_hand);
        $this->assertSame(2, (int) $inv->damaged);
        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::DamagedStockRecorded->value,
        ]);
    }

    public function test_stock_count_difference_and_approval(): void
    {
        $admin = Admin::factory()->superAdmin()->create(['is_active' => true]);
        Sanctum::actingAs($admin);
        $store = $this->stores->create(['code' => 'TZUR', 'name' => 'Tzur Jewelry']);
        $sku = $this->seedSku($store, 15000, 100);

        $created = $this->postJson('/api/v1/admin/inventory/counts', [
            'store_id' => $store->id,
            'scope' => 'full',
        ])->assertCreated();

        $countId = $created->json('data.id');
        $lineId = $created->json('data.lines.0.id');
        $this->assertNotEmpty($lineId);

        $this->postJson("/api/v1/admin/inventory/counts/{$countId}/lines", [
            'lines' => [[
                'line_id' => $lineId,
                'counted_quantity' => 97,
                'reason' => 'Shrinkage found',
            ]],
        ])->assertOk()
            ->assertJsonPath('data.lines.0.difference', -3);

        $this->postJson("/api/v1/admin/inventory/counts/{$countId}/submit")->assertOk()
            ->assertJsonPath('data.status', InventoryCountStatus::PendingApproval->value);

        $this->postJson("/api/v1/admin/inventory/counts/{$countId}/approve", [
            'reason' => 'Approved variance',
        ])->assertOk()
            ->assertJsonPath('data.status', InventoryCountStatus::Approved->value);

        $this->assertSame(97, (int) VariantInventory::query()->findOrFail($sku['inventory_id'])->on_hand);
        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::StockCountCompleted->value,
        ]);
    }

    public function test_damaged_stock_unavailable_for_pos_and_movement_ledger(): void
    {
        $super = Admin::factory()->superAdmin()->create();
        $store = $this->stores->create(['code' => 'PEACH', 'name' => 'Peachy Lingerie']);
        $cashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
            'is_active' => true,
        ]);
        $this->assignments->assign($cashier, $store, $super);
        $sku = $this->seedSku($store, 8000, 5);

        Sanctum::actingAs($super);
        app(InventoryControlEngine::class)->adjust(
            $store,
            $sku['variant_id'],
            -5,
            'All damaged',
            $super,
            'damage',
        );

        $this->assertSame(0, (int) VariantInventory::query()->findOrFail($sku['inventory_id'])->available());
        $this->assertSame(5, (int) VariantInventory::query()->findOrFail($sku['inventory_id'])->damaged);

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $store->id,
            'terminal_id' => $store->terminals()->firstOrFail()->id,
            'opening_float' => 10000,
        ])->assertCreated();

        $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [[
                'product_id' => $sku['product_id'],
                'product_variant_id' => $sku['variant_id'],
                'quantity' => 1,
            ]],
            'payment_method' => 'CASH',
            'amount_received' => 8000,
        ])->assertStatus(422);

        $this->assertTrue(
            InventoryStockMovement::query()
                ->where('product_variant_id', $sku['variant_id'])
                ->where('movement_type', InventoryMovementType::Damage)
                ->exists()
        );
    }

    public function test_valuation_and_store_isolation(): void
    {
        $super = Admin::factory()->superAdmin()->create(['is_active' => true]);
        $zion = $this->stores->create(['code' => 'ZION2', 'name' => 'Zion']);
        $rovi = $this->stores->create(['code' => 'ROVI2', 'name' => 'Rovi']);
        $this->seedSku($zion, 10000, 10, cost: 4000);
        $this->seedSku($rovi, 20000, 5, cost: 8000);

        $cashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
            'is_active' => true,
        ]);
        $this->assignments->assign($cashier, $zion, $super);

        Sanctum::actingAs($cashier);
        $zionVal = $this->getJson('/api/v1/admin/inventory/valuation?store_id='.$zion->id)
            ->assertOk()
            ->json('data.summary.total_cost_value');
        $this->assertEquals(40000.0, (float) $zionVal);

        $this->getJson('/api/v1/admin/inventory/valuation?store_id='.$rovi->id)
            ->assertStatus(422);

        Sanctum::actingAs($super);
        $all = $this->getJson('/api/v1/admin/inventory/valuation')->assertOk()->json('data.summary');
        $this->assertGreaterThanOrEqual(2, (int) $all['sku_count']);

        $this->getJson('/api/v1/admin/analytics/inventory')
            ->assertOk()
            ->assertJsonStructure(['data' => ['summary' => ['inventory_value', 'low_stock']]]);
    }

    public function test_permission_guest_rejected(): void
    {
        $this->getJson('/api/v1/admin/inventory')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/inventory')->assertUnauthorized();
    }

    /**
     * @return array{product_id: string, variant_id: string, inventory_id: string}
     */
    private function seedSku(Store $store, float $price, int $stock, float $cost = 0): array
    {
        $product = Product::factory()->create([
            'store_id' => $store->id,
            'commerce_channel_id' => $this->tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'price' => $price,
            'cost_price' => $cost > 0 ? $cost : round($price * 0.5, 2),
            'is_active' => true,
            'is_demo' => false,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Default',
            'price' => $price,
            'is_active' => true,
        ]);
        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => $price,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        $location = $this->stores->defaultLocation($store);
        $inventory = VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'inventory_location_id' => $location->id,
            'warehouse_code' => $location->code,
            'on_hand' => $stock,
            'reserved' => 0,
            'damaged' => 0,
            'inspection' => 0,
            'reorder_level' => 5,
            'is_active' => true,
        ]);

        return [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'inventory_id' => $inventory->id,
        ];
    }
}

<?php

namespace Tests\Feature\China;

use App\Enums\ChinaWorkflowStage;
use App\Enums\FulfillmentStrategy;
use App\Enums\OrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\China\ChinaWorkflowEngine;
use App\Services\Fulfillment\FulfillmentEngine;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ChinaSupplierResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_simple_product_order_uses_product_supplier_id_for_procurement(): void
    {
        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $product = Product::factory()->create([
            'fulfillment_source' => 'imported_from_china',
            'supplier_id' => $supplier->id,
            'cost_price' => 12000,
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
            'unit_price' => 30000,
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

    public function test_variant_product_still_uses_active_supplier_product_mapping(): void
    {
        $supplier = Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        $product = Product::factory()->create([
            'fulfillment_source' => 'imported_from_china',
            'supplier_id' => Supplier::factory()->create(['is_active' => true])->id,
        ]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_variant_id' => $variant->id,
            'supplier_sku' => 'CN-MAP-1',
            'purchase_cost' => 9000,
            'currency' => 'TZS',
            'lead_time_days' => 5,
            'is_active' => true,
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
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $fulfillment = app(FulfillmentEngine::class)->createForOrder($order->fresh(['items.product', 'items.variant']));
        app(ChinaWorkflowEngine::class)->bootstrapFromFulfillment($fulfillment);

        $po = PurchaseOrder::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame($supplier->id, $po->supplier_id);
        $this->assertSame(
            '9000.00',
            (string) $po->items()->first()->unit_cost,
        );
    }

    public function test_unmapped_items_keep_existing_supplier_validation_error(): void
    {
        $product = Product::factory()->create([
            'fulfillment_source' => 'imported_from_china',
            'supplier_id' => null,
        ]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $fulfillment = app(FulfillmentEngine::class)->createForOrder($order->fresh(['items.product', 'items.variant']));

        try {
            app(ChinaWorkflowEngine::class)->bootstrapFromFulfillment($fulfillment);
            $this->fail('Expected supplier mapping validation failure.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'No internal supplier mapping found for China order items. Map SupplierProduct or Product.supplier_id first.',
                $exception->errors()['suppliers'][0] ?? null,
            );
        }

        $this->assertSame(0, PurchaseOrder::query()->where('order_id', $order->id)->count());
    }
}

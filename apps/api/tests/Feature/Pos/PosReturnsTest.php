<?php

namespace Tests\Feature\Pos;

use App\Enums\ActivityEventType;
use App\Enums\CommerceChannelCode;
use App\Enums\InventoryDisposition;
use App\Enums\PosPaymentHandler;
use App\Enums\VariantPriceType;
use App\Models\Admin;
use App\Models\CommerceChannel;
use App\Models\PaymentMethodDefinition;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProfitRecord;
use App\Models\ReturnReason;
use App\Models\Role;
use App\Models\User;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use App\Services\Stores\StoreAssignmentService;
use App\Services\Stores\StoreService;
use Database\Seeders\ReturnReasonSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosReturnsTest extends TestCase
{
    use RefreshDatabase;

    private StoreService $stores;

    private StoreAssignmentService $assignments;

    private CommerceChannel $tz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(ReturnReasonSeeder::class);
        $this->stores = app(StoreService::class);
        $this->assignments = app(StoreAssignmentService::class);

        $this->tz = CommerceChannel::query()->updateOrCreate(
            ['code' => CommerceChannelCode::TzLocal->value],
            ['name' => 'Buy From TZ', 'description' => 'Local', 'is_active' => true],
        );

        foreach ([
            ['CASH', PosPaymentHandler::CashWithChange],
            ['MPESA_LIPA', PosPaymentHandler::ManualConfirm],
            ['NMB_BANK', PosPaymentHandler::ManualConfirm],
        ] as [$code, $handler]) {
            PaymentMethodDefinition::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $code,
                    'is_active' => true,
                    'sort_order' => 1,
                    'config' => ['handler' => $handler->value, 'pos_enabled' => true],
                ],
            );
        }
    }

    private function cashier(\App\Models\Store $store): Admin
    {
        $super = Admin::factory()->superAdmin()->create();
        $cashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
        ]);
        $this->assignments->assign($cashier, $store, $super);

        return $cashier;
    }

    private function sellable(string $storeId, float $price = 50000, int $stock = 10, array $attrs = []): array
    {
        $variantName = $attrs['variant_name'] ?? 'Size M';
        unset($attrs['variant_name']);

        $product = Product::factory()->create(array_merge([
            'store_id' => $storeId,
            'commerce_channel_id' => $this->tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'price' => $price,
            'is_active' => true,
            'is_demo' => false,
        ], $attrs));

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => $price,
            'is_active' => true,
            'name' => $variantName,
        ]);

        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => $price,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);

        $store = \App\Models\Store::query()->findOrFail($storeId);
        $inventory = VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'inventory_location_id' => $store->defaultInventoryLocation->id,
            'warehouse_code' => $store->defaultInventoryLocation->code,
            'on_hand' => $stock,
            'reserved' => 0,
            'is_active' => true,
        ]);

        return compact('product', 'variant', 'inventory');
    }

    private function openSession(Admin $cashier, \App\Models\Store $store): void
    {
        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $store->id,
            'terminal_id' => $store->terminals()->firstOrFail()->id,
            'opening_float' => 100000,
        ])->assertCreated();
    }

    private function completeSale(array $sku, int $qty = 2, array $extra = []): array
    {
        $sale = $this->postJson('/api/v1/admin/pos/sales', array_merge([
            'items' => [[
                'product_id' => $sku['product']->id,
                'product_variant_id' => $sku['variant']->id,
                'quantity' => $qty,
            ]],
            'payment_method' => 'CASH',
            'amount_received' => 50000 * $qty,
        ], $extra))->assertCreated();

        return [
            'order_id' => $sale->json('data.order.id'),
            'order_number' => $sale->json('data.order.order_number'),
            'receipt_number' => $sale->json('data.receipt.receipt_number'),
            'order_item_id' => \App\Models\OrderItem::query()
                ->where('order_id', $sale->json('data.order.id'))
                ->value('id'),
        ];
    }

    public function test_receipt_lookup_and_full_cash_return_restocks_inventory(): void
    {
        $store = $this->stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $cashier = $this->cashier($store);
        $sku = $this->sellable($store->id, 50000, 10);
        $this->openSession($cashier, $store);
        $sale = $this->completeSale($sku, 2);

        $this->assertSame(8, $sku['inventory']->fresh()->on_hand);

        $search = $this->getJson('/api/v1/admin/pos/returns/search?receipt_number='.$sale['receipt_number'])
            ->assertOk();
        $this->assertTrue($search->json('data.0.eligible'));

        $reasonId = ReturnReason::query()->where('code', 'CHANGED_MIND')->value('id');
        $this->assertDatabaseHas('profit_records', ['order_id' => $sale['order_id']]);

        $ret = $this->postJson('/api/v1/admin/pos/returns', [
            'order_id' => $sale['order_id'],
            'return_type' => 'refund',
            'return_reason_id' => $reasonId,
            'refund_method' => 'CASH',
            'items' => [[
                'order_item_id' => $sale['order_item_id'],
                'quantity' => 2,
                'inventory_disposition' => InventoryDisposition::Sellable->value,
            ]],
        ])->assertCreated();

        $ret->assertJsonPath('data.return.refund_total', '100000.00')
            ->assertJsonPath('data.return.return_type', 'refund')
            ->assertJsonPath('data.refund.method', 'CASH');

        $this->assertSame(10, $sku['inventory']->fresh()->on_hand);
        $this->assertSame('0.00', (string) ProfitRecord::query()->where('order_id', $sale['order_id'])->value('revenue'));

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::PosReturnCompleted->value,
            'subject_id' => $ret->json('data.return.id'),
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::PosRefundIssued->value,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::PosInventoryReturned->value,
        ]);

        $this->assertNotNull($ret->json('data.return.receipt_snapshot.return_number'));
        $this->assertSame($sale['receipt_number'], $ret->json('data.return.receipt_snapshot.original_receipt_number'));
    }

    public function test_partial_return_and_quantity_validation(): void
    {
        $store = $this->stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $cashier = $this->cashier($store);
        $sku = $this->sellable($store->id, 20000, 5);
        $this->openSession($cashier, $store);
        $sale = $this->completeSale($sku, 3, ['amount_received' => 60000]);

        $reasonId = ReturnReason::query()->value('id');

        $this->postJson('/api/v1/admin/pos/returns', [
            'order_id' => $sale['order_id'],
            'return_type' => 'refund',
            'return_reason_id' => $reasonId,
            'refund_method' => 'MPESA_LIPA',
            'items' => [[
                'order_item_id' => $sale['order_item_id'],
                'quantity' => 1,
                'inventory_disposition' => 'sellable',
            ]],
        ])->assertCreated()->assertJsonPath('data.return.refund_total', '20000.00');

        $this->assertSame(3, $sku['inventory']->fresh()->on_hand); // 5-3+1

        $this->postJson('/api/v1/admin/pos/returns', [
            'order_id' => $sale['order_id'],
            'return_type' => 'refund',
            'return_reason_id' => $reasonId,
            'refund_method' => 'CASH',
            'items' => [[
                'order_item_id' => $sale['order_item_id'],
                'quantity' => 3,
            ]],
        ])->assertStatus(422);
    }

    public function test_damaged_path_does_not_restock(): void
    {
        $store = $this->stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $cashier = $this->cashier($store);
        $sku = $this->sellable($store->id, 15000, 4);
        $this->openSession($cashier, $store);
        $sale = $this->completeSale($sku, 1, ['amount_received' => 15000]);

        $this->postJson('/api/v1/admin/pos/returns', [
            'order_id' => $sale['order_id'],
            'return_type' => 'refund',
            'return_reason_id' => ReturnReason::query()->where('code', 'DAMAGED')->value('id'),
            'refund_method' => 'CASH',
            'items' => [[
                'order_item_id' => $sale['order_item_id'],
                'quantity' => 1,
                'inventory_disposition' => InventoryDisposition::Damaged->value,
            ]],
        ])->assertCreated();

        $this->assertSame(3, $sku['inventory']->fresh()->on_hand);
        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::PosInventoryMarkedDamaged->value,
        ]);
    }

    public function test_exchange_same_product_different_variant(): void
    {
        $store = $this->stores->create(['code' => 'TZUR', 'name' => 'Tzur']);
        $cashier = $this->cashier($store);
        $skuM = $this->sellable($store->id, 30000, 5, ['variant_name' => 'Size M']);
        $skuL = ProductVariant::factory()->create([
            'product_id' => $skuM['product']->id,
            'name' => 'Size L',
            'price' => 30000,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $skuL->id,
            'inventory_location_id' => $store->defaultInventoryLocation->id,
            'warehouse_code' => $store->defaultInventoryLocation->code,
            'on_hand' => 5,
            'reserved' => 0,
            'is_active' => true,
        ]);

        $this->openSession($cashier, $store);
        $sale = $this->completeSale($skuM, 1, ['amount_received' => 30000]);

        $this->postJson('/api/v1/admin/pos/returns', [
            'order_id' => $sale['order_id'],
            'return_type' => 'exchange',
            'return_reason_id' => ReturnReason::query()->where('code', 'WRONG_SIZE')->value('id'),
            'items' => [[
                'order_item_id' => $sale['order_item_id'],
                'quantity' => 1,
                'inventory_disposition' => 'sellable',
                'exchange_variant_id' => $skuL->id,
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.return.return_type', 'exchange');

        $this->assertSame(5, $skuM['inventory']->fresh()->on_hand); // 5-1+1
        $this->assertSame(4, VariantInventory::query()->where('product_variant_id', $skuL->id)->value('on_hand'));
        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::PosExchangeCompleted->value,
        ]);
    }

    public function test_store_isolation_and_crm_customer_return(): void
    {
        $zion = $this->stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $tzur = $this->stores->create(['code' => 'TZUR', 'name' => 'Tzur']);
        $zionCashier = $this->cashier($zion);
        $tzurCashier = $this->cashier($tzur);
        $customer = User::factory()->create(['name' => 'Amina']);

        $sku = $this->sellable($zion->id, 10000, 3);
        Sanctum::actingAs($zionCashier);
        $this->openSession($zionCashier, $zion);
        $sale = $this->completeSale($sku, 1, [
            'amount_received' => 10000,
            'customer_id' => $customer->id,
        ]);

        Sanctum::actingAs($tzurCashier);
        $this->openSession($tzurCashier, $tzur);
        $this->postJson('/api/v1/admin/pos/returns', [
            'order_id' => $sale['order_id'],
            'return_type' => 'refund',
            'return_reason_id' => ReturnReason::query()->value('id'),
            'refund_method' => 'CASH',
            'items' => [[
                'order_item_id' => $sale['order_item_id'],
                'quantity' => 1,
            ]],
        ])->assertStatus(422);

        Sanctum::actingAs($zionCashier);
        // reopen if closed - session still open
        $this->postJson('/api/v1/admin/pos/returns', [
            'order_id' => $sale['order_id'],
            'return_type' => 'refund',
            'return_reason_id' => ReturnReason::query()->value('id'),
            'refund_method' => 'NMB_BANK',
            'items' => [[
                'order_item_id' => $sale['order_item_id'],
                'quantity' => 1,
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.return.receipt_snapshot.customer', 'Amina');
    }

    public function test_duplicate_over_return_rejected(): void
    {
        $store = $this->stores->create(['code' => 'ROVI', 'name' => 'Rovi']);
        $cashier = $this->cashier($store);
        $sku = $this->sellable($store->id, 12000, 4);
        $this->openSession($cashier, $store);
        $sale = $this->completeSale($sku, 1, ['amount_received' => 12000]);
        $reasonId = ReturnReason::query()->value('id');

        $this->postJson('/api/v1/admin/pos/returns', [
            'order_id' => $sale['order_id'],
            'return_type' => 'refund',
            'return_reason_id' => $reasonId,
            'refund_method' => 'CASH',
            'items' => [[
                'order_item_id' => $sale['order_item_id'],
                'quantity' => 1,
            ]],
        ])->assertCreated();

        $this->postJson('/api/v1/admin/pos/returns', [
            'order_id' => $sale['order_id'],
            'return_type' => 'refund',
            'return_reason_id' => $reasonId,
            'refund_method' => 'CASH',
            'items' => [[
                'order_item_id' => $sale['order_item_id'],
                'quantity' => 1,
            ]],
        ])->assertStatus(422);
    }

    public function test_pos_item_level_idempotency_prevents_double_restock(): void
    {
        $store = $this->stores->create(['code' => 'IDEM', 'name' => 'Idem']);
        $cashier = $this->cashier($store);
        $sku = $this->sellable($store->id, 20000, 6);
        $this->openSession($cashier, $store);
        $sale = $this->completeSale($sku, 2, ['amount_received' => 40000]);

        $ret = $this->postJson('/api/v1/admin/pos/returns', [
            'order_id' => $sale['order_id'],
            'return_type' => 'refund',
            'return_reason_id' => ReturnReason::query()->value('id'),
            'refund_method' => 'CASH',
            'items' => [[
                'order_item_id' => $sale['order_item_id'],
                'quantity' => 2,
                'inventory_disposition' => InventoryDisposition::Sellable->value,
            ]],
        ])->assertCreated();

        $this->assertSame(6, (int) $sku['inventory']->fresh()->on_hand);

        $returnItemId = \App\Models\ReturnItem::query()
            ->where('return_request_id', $ret->json('data.return.id'))
            ->value('id');

        app(\App\Services\Inventory\InventoryControlEngine::class)->recordReturn(
            $sku['inventory']->fresh(),
            2,
            $cashier,
            \App\Models\ReturnItem::class,
            $returnItemId,
            'return-restock:'.$returnItemId,
        );

        $this->assertSame(6, (int) $sku['inventory']->fresh()->on_hand);
    }
}

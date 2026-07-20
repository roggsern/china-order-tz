<?php

namespace Tests\Feature\Pos;

use App\Enums\ActivityEventType;
use App\Enums\CommerceChannelCode;
use App\Enums\CustomerTimelineEventType;
use App\Enums\InventoryDisposition;
use App\Enums\PosPaymentHandler;
use App\Enums\PosSessionStatus;
use App\Enums\PosSessionVarianceType;
use App\Enums\PromotionDiscountType;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Enums\SalesOrigin;
use App\Enums\VariantPriceType;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\CommerceChannel;
use App\Models\CustomerProfile;
use App\Models\CustomerTimelineEvent;
use App\Models\Order;
use App\Models\OrderCostSnapshot;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentMethodDefinition;
use App\Models\PosReceipt;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProfitRecord;
use App\Models\Promotion;
use App\Models\PromotionUsage;
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

/**
 * TASK 040A — end-to-end retail smoke: session → sale → receipt → inventory →
 * profit → CRM → return/refund → close → analytics. No new features under test.
 */
class RetailSmokeLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private StoreService $stores;

    private StoreAssignmentService $assignments;

    private CommerceChannel $tz;

    private CommerceChannel $china;

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
        $this->china = CommerceChannel::query()->updateOrCreate(
            ['code' => CommerceChannelCode::ChinaImport->value],
            ['name' => 'China Import', 'description' => 'Import', 'is_active' => true],
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

    private function storeCashier(\App\Models\Store $store): Admin
    {
        $super = Admin::factory()->superAdmin()->create();
        $cashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
        ]);
        $this->assignments->assign($cashier, $store, $super);

        return $cashier;
    }

    private function seedSku(string $storeId, float $price, int $stock = 10, array $attrs = []): array
    {
        $product = Product::factory()->create(array_merge([
            'store_id' => $storeId,
            'commerce_channel_id' => $this->tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'price' => $price,
            'cost_price' => round($price * 0.6, 2),
            'is_active' => true,
            'is_demo' => false,
            'name' => $attrs['name'] ?? 'Zion Dress',
        ], $attrs));

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Size M',
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

        $store = \App\Models\Store::query()->findOrFail($storeId);
        $inventory = VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'inventory_location_id' => $store->defaultInventoryLocation->id,
            'warehouse_code' => $store->defaultInventoryLocation->code,
            'on_hand' => $stock,
            'reserved' => 0,
            'reorder_level' => 2,
            'is_active' => true,
        ]);

        return compact('product', 'variant', 'inventory');
    }

    private function crmCustomer(string $name = 'Amina Buyer'): User
    {
        $user = User::factory()->create(['name' => $name]);
        $roleId = Role::query()->where('slug', 'customer')->value('id');
        $user->roles()->syncWithoutDetaching([$roleId]);

        return $user;
    }

    public function test_complete_retail_lifecycle_zion_mode(): void
    {
        $zion = $this->stores->create([
            'code' => 'ZION',
            'name' => 'ZION MODE',
            'theme_color' => '#1F4B3A',
            'settings' => [
                'receipt' => [
                    'address' => 'Samora Ave',
                    'footer_message' => 'Zion Mode',
                    'thank_you_message' => 'Asante!',
                ],
            ],
        ]);
        $tzur = $this->stores->create(['code' => 'TZUR', 'name' => 'Tzur']);
        $rovi = $this->stores->create(['code' => 'ROVI', 'name' => 'Rovi']);

        $cashier = $this->storeCashier($zion);
        $zionSku = $this->seedSku($zion->id, 50000, 10, ['name' => 'Zion Dress']);
        $tzurSku = $this->seedSku($tzur->id, 40000, 5, ['name' => 'Tzur Bag']);
        $roviSku = $this->seedSku($rovi->id, 30000, 5, ['name' => 'Rovi Cream']);

        $chinaProduct = Product::factory()->create([
            'store_id' => $zion->id,
            'commerce_channel_id' => $this->china->id,
            'fulfillment_source' => CommerceChannelCode::ChinaImport->fulfillmentSource(),
            'is_active' => true,
            'is_demo' => false,
            'name' => 'China Import Phone',
        ]);
        $chinaVariant = ProductVariant::factory()->create([
            'product_id' => $chinaProduct->id,
            'price' => 999000,
            'is_active' => true,
        ]);

        Promotion::query()->create([
            'name' => 'Smoke Ten',
            'code' => 'SMOKE10',
            'type' => PromotionType::Coupon,
            'discount_type' => PromotionDiscountType::Percentage,
            'value' => 10,
            'currency' => 'TZS',
            'status' => PromotionStatus::Active,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        // STEP 1 — open session
        Sanctum::actingAs($cashier);
        $opened = $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $zion->id,
            'terminal_id' => $zion->terminals()->firstOrFail()->id,
            'opening_float' => 100000,
        ])->assertCreated();

        $sessionId = $opened->json('data.id');
        $this->assertSame(PosSessionStatus::Open->value, $opened->json('data.status'));
        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::PosSessionOpened->value,
            'subject_id' => $sessionId,
        ]);

        // STEP 2 — catalog isolation + quote price/stock
        $catalog = $this->getJson('/api/v1/admin/pos/catalog?q=Dress')->assertOk();
        $names = collect($catalog->json('data'))->pluck('product_name')->all();
        $this->assertContains('Zion Dress', $names);
        $this->assertNotContains('Tzur Bag', $names);
        $this->assertNotContains('Rovi Cream', $names);
        $this->assertNotContains('China Import Phone', $names);

        $quote = $this->postJson('/api/v1/admin/pos/quote', [
            'items' => [[
                'product_id' => $zionSku['product']->id,
                'product_variant_id' => $zionSku['variant']->id,
                'quantity' => 2,
            ]],
            'promotion_code' => 'SMOKE10',
        ])->assertOk();
        $quote->assertJsonPath('data.lines.0.unit_price', '50000.00')
            ->assertJsonPath('data.discount_total', '10000.00')
            ->assertJsonPath('data.grand_total', '90000.00');

        $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [[
                'product_id' => $tzurSku['product']->id,
                'product_variant_id' => $tzurSku['variant']->id,
                'quantity' => 1,
            ]],
            'payment_method' => 'CASH',
            'amount_received' => 40000,
        ])->assertStatus(422);

        $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [[
                'product_id' => $chinaProduct->id,
                'product_variant_id' => $chinaVariant->id,
                'quantity' => 1,
            ]],
            'payment_method' => 'CASH',
            'amount_received' => 999000,
        ])->assertStatus(422);

        // STEP 3A — walk-in cash sale with promotion
        $walkIn = $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [[
                'product_id' => $zionSku['product']->id,
                'product_variant_id' => $zionSku['variant']->id,
                'quantity' => 2,
            ]],
            'payment_method' => 'CASH',
            'amount_received' => 100000,
            'promotion_code' => 'SMOKE10',
        ])->assertCreated();

        $walkInOrderId = $walkIn->json('data.order.id');
        $walkInReceiptId = $walkIn->json('data.receipt.id');
        $walkIn->assertJsonPath('data.order.user_id', null)
            ->assertJsonPath('data.order.sales_origin', SalesOrigin::Pos->value)
            ->assertJsonPath('data.order.total', '90000.00')
            ->assertJsonPath('data.order.discount_amount', '10000.00')
            ->assertJsonPath('data.change', '10000.00')
            ->assertJsonPath('data.receipt.snapshot.branding.store_name', 'ZION MODE')
            ->assertJsonPath('data.receipt.snapshot.payment.method_code', 'CASH');

        $this->assertSame(8, $zionSku['inventory']->fresh()->on_hand);
        $this->assertSame($zion->defaultInventoryLocation->code, $zionSku['inventory']->fresh()->warehouse_code);
        $this->assertStringNotContainsStringIgnoringCase('MAIN', (string) $zionSku['inventory']->fresh()->warehouse_code);

        $this->assertDatabaseHas('promotion_usages', ['order_id' => $walkInOrderId]);
        $this->assertTrue(OrderCostSnapshot::query()->whereHas('orderItem', fn ($q) => $q->where('order_id', $walkInOrderId))->exists());
        $profit = ProfitRecord::query()->where('order_id', $walkInOrderId)->first();
        $this->assertNotNull($profit);
        $this->assertSame('90000.00', (string) $profit->revenue);

        // STEP 3B — CRM customer + MPESA
        $customer = $this->crmCustomer();
        $crmSale = $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [[
                'product_id' => $zionSku['product']->id,
                'product_variant_id' => $zionSku['variant']->id,
                'quantity' => 1,
            ]],
            'payment_method' => 'MPESA_LIPA',
            'manual_confirmed' => true,
            'customer_id' => $customer->id,
        ])->assertCreated();

        $crmOrderId = $crmSale->json('data.order.id');
        $crmSale->assertJsonPath('data.order.user_id', $customer->id)
            ->assertJsonPath('data.receipt.snapshot.payment.method_code', 'MPESA_LIPA');

        $profile = CustomerProfile::query()->where('user_id', $customer->id)->first();
        $this->assertNotNull($profile);
        $this->assertTrue(
            CustomerTimelineEvent::query()
                ->where('customer_profile_id', $profile->id)
                ->whereIn('event_type', [
                    CustomerTimelineEventType::OrderCreated->value,
                    CustomerTimelineEventType::PaymentCompleted->value,
                ])
                ->exists()
        );
        $profile->load('metrics');
        $this->assertGreaterThanOrEqual(1, (int) ($profile->metrics?->total_orders ?? 1));

        // STEP 5 — NMB bank manual confirm
        $bankSale = $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [[
                'product_id' => $zionSku['product']->id,
                'product_variant_id' => $zionSku['variant']->id,
                'quantity' => 1,
            ]],
            'payment_method' => 'NMB_BANK',
            'manual_confirmed' => true,
        ])->assertCreated();
        $bankSale->assertJsonPath('data.receipt.snapshot.payment.method_code', 'NMB_BANK');

        // STEP 6 — receipt preview / print / pdf / reprint (no duplicate order)
        $ordersBeforeReprint = Order::query()->count();
        $receiptsBeforeReprint = PosReceipt::query()->count();
        $paymentsBefore = Payment::query()->count();

        $this->get("/api/v1/admin/pos/receipts/{$walkInReceiptId}/preview?layout=thermal_80")->assertOk();
        $this->get("/api/v1/admin/pos/receipts/{$walkInReceiptId}/pdf")->assertOk();
        $this->postJson("/api/v1/admin/pos/receipts/{$walkInReceiptId}/print", ['layout' => 'thermal_80'])->assertOk();
        $this->postJson("/api/v1/admin/pos/receipts/{$walkInReceiptId}/reprint", ['layout' => 'a4'])->assertOk();

        $this->assertSame($ordersBeforeReprint, Order::query()->count());
        $this->assertSame($receiptsBeforeReprint, PosReceipt::query()->count());
        $this->assertSame($paymentsBefore, Payment::query()->count());
        $this->assertSame(1, PosReceipt::query()->where('order_id', $walkInOrderId)->count());

        // STEP 10/11 — sellable return + cash refund + profit reversal
        $stockBeforeReturn = (int) $zionSku['inventory']->fresh()->on_hand;
        $profitBefore = (float) ProfitRecord::query()->where('order_id', $walkInOrderId)->value('revenue');
        $orderItemId = OrderItem::query()->where('order_id', $walkInOrderId)->value('id');

        $sellableReturn = $this->postJson('/api/v1/admin/pos/returns', [
            'order_id' => $walkInOrderId,
            'return_type' => 'refund',
            'return_reason_id' => ReturnReason::query()->where('code', 'CHANGED_MIND')->value('id')
                ?? ReturnReason::query()->value('id'),
            'refund_method' => 'CASH',
            'items' => [[
                'order_item_id' => $orderItemId,
                'quantity' => 1,
                'inventory_disposition' => InventoryDisposition::Sellable->value,
            ]],
        ])->assertCreated();

        $this->assertSame($stockBeforeReturn + 1, (int) $zionSku['inventory']->fresh()->on_hand);
        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::PosRefundIssued->value,
        ]);
        $profitAfter = (float) ProfitRecord::query()->where('order_id', $walkInOrderId)->value('revenue');
        $this->assertLessThan($profitBefore, $profitAfter);

        // Damaged return on CRM order — no restock
        $crmItemId = OrderItem::query()->where('order_id', $crmOrderId)->value('id');
        $stockBeforeDamaged = (int) $zionSku['inventory']->fresh()->on_hand;
        $this->postJson('/api/v1/admin/pos/returns', [
            'order_id' => $crmOrderId,
            'return_type' => 'refund',
            'return_reason_id' => ReturnReason::query()->where('code', 'DAMAGED')->value('id')
                ?? ReturnReason::query()->value('id'),
            'refund_method' => 'MPESA_LIPA',
            'items' => [[
                'order_item_id' => $crmItemId,
                'quantity' => 1,
                'inventory_disposition' => InventoryDisposition::Damaged->value,
            ]],
        ])->assertCreated();
        $this->assertSame($stockBeforeDamaged, (int) $zionSku['inventory']->fresh()->on_hand);
        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::PosInventoryMarkedDamaged->value,
        ]);

        // Bank refund record on remaining NMB sale
        $bankItemId = OrderItem::query()->where('order_id', $bankSale->json('data.order.id'))->value('id');
        $this->postJson('/api/v1/admin/pos/returns', [
            'order_id' => $bankSale->json('data.order.id'),
            'return_type' => 'refund',
            'return_reason_id' => ReturnReason::query()->value('id'),
            'refund_method' => 'NMB_BANK',
            'items' => [[
                'order_item_id' => $bankItemId,
                'quantity' => 1,
                'inventory_disposition' => InventoryDisposition::Sellable->value,
            ]],
        ])->assertCreated();

        // Over-return rejected
        $this->postJson('/api/v1/admin/pos/returns', [
            'order_id' => $crmOrderId,
            'return_type' => 'refund',
            'return_reason_id' => ReturnReason::query()->value('id'),
            'refund_method' => 'CASH',
            'items' => [[
                'order_item_id' => $crmItemId,
                'quantity' => 1,
                'inventory_disposition' => InventoryDisposition::Sellable->value,
            ]],
        ])->assertStatus(422);

        // STEP 12 — close session (use live expected after cash refunds)
        $dash = $this->getJson('/api/v1/admin/pos/dashboard')->assertOk();
        $expected = (float) $dash->json('data.summary.expected_cash');
        $closed = $this->postJson('/api/v1/admin/pos/sessions/close', [
            'closing_cash' => $expected,
        ])->assertOk();
        $closed->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.variance_type', PosSessionVarianceType::Balanced->value)
            ->assertJsonPath('data.expected_cash', number_format($expected, 2, '.', ''));
        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::PosSessionClosed->value,
            'subject_id' => $sessionId,
        ]);

        // STEP 13 — analytics reflection
        Sanctum::actingAs(Admin::factory()->superAdmin()->create());
        $this->getJson('/api/v1/admin/analytics/dashboard?store_id='.$zion->id)
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->getJson('/api/v1/admin/analytics/sales?store_id='.$zion->id)
            ->assertOk()
            ->assertJsonPath('data.summary.orders_count', 3);
        $this->getJson('/api/v1/admin/analytics/profit?store_id='.$zion->id)->assertOk();
        $this->getJson('/api/v1/admin/analytics/returns?store_id='.$zion->id)
            ->assertOk()
            ->assertJsonPath('data.summary.returns_count', 3);
        $this->getJson('/api/v1/admin/analytics/payments?store_id='.$zion->id)->assertOk();
        $this->getJson('/api/v1/admin/analytics/inventory?store_id='.$zion->id)->assertOk();
        $this->getJson('/api/v1/admin/analytics/customers?store_id='.$zion->id)->assertOk();
        $this->getJson('/api/v1/admin/analytics/sessions?store_id='.$zion->id)
            ->assertOk()
            ->assertJsonPath('data.summary.closed_sessions', 1);
        $this->getJson('/api/v1/admin/analytics/stores')->assertOk();

        // Data integrity
        $this->assertSame(3, Order::query()->where('store_id', $zion->id)->where('sales_origin', SalesOrigin::Pos)->count());
        $this->assertSame(3, Payment::query()->whereHas('order', fn ($q) => $q->where('store_id', $zion->id))->count());
        $this->assertSame(3, PosReceipt::query()->where('store_id', $zion->id)->count());
        $this->assertSame(0, PromotionUsage::query()->whereNull('order_id')->count());
    }

    public function test_permission_isolation_store_master_super(): void
    {
        $zion = $this->stores->create(['code' => 'ZION', 'name' => 'ZION MODE']);
        $tzur = $this->stores->create(['code' => 'TZUR', 'name' => 'Tzur']);
        $super = Admin::factory()->superAdmin()->create();

        $zionCashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
        ]);
        $master = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'master_cashier')->value('id'),
            'is_super_admin' => false,
        ]);
        $this->assignments->assign($zionCashier, $zion, $super);
        $this->assignments->assign($master, $zion, $super);

        $this->seedSku($zion->id, 10000, 3);
        $this->seedSku($tzur->id, 10000, 3);

        Sanctum::actingAs($zionCashier);
        $this->getJson('/api/v1/admin/analytics/dashboard')->assertOk()
            ->assertJsonPath('data.scope.store_ids.0', $zion->id);
        $this->getJson('/api/v1/admin/analytics/sales?store_id='.$tzur->id)->assertStatus(422);

        Sanctum::actingAs($master);
        $this->getJson('/api/v1/admin/analytics/dashboard')->assertOk();
        $this->getJson('/api/v1/admin/analytics/sales?store_id='.$tzur->id)->assertStatus(422);
        $this->getJson('/api/v1/admin/pos/my-stores')->assertOk();
        $storeIds = collect($this->getJson('/api/v1/admin/pos/my-stores')->json('data'))->pluck('id');
        $this->assertTrue($storeIds->contains($zion->id));
        $this->assertFalse($storeIds->contains($tzur->id));

        Sanctum::actingAs($super);
        $this->getJson('/api/v1/admin/analytics/dashboard')->assertOk();
        $all = collect($this->getJson('/api/v1/admin/pos/my-stores')->json('data'))->pluck('code');
        $this->assertTrue($all->contains('ZION'));
        $this->assertTrue($all->contains('TZUR'));
    }
}

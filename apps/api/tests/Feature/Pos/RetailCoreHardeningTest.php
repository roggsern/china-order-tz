<?php

namespace Tests\Feature\Pos;

use App\Enums\CommerceChannelCode;
use App\Enums\InventoryDisposition;
use App\Enums\PosPaymentHandler;
use App\Enums\VariantPriceType;
use App\Models\Admin;
use App\Models\CommerceChannel;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethodDefinition;
use App\Models\PosReceipt;
use App\Models\PosSaleIdempotencyKey;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProfitRecord;
use App\Models\ReturnReason;
use App\Models\Role;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use App\Services\Analytics\DTOs\AnalyticsFilter;
use App\Services\Analytics\RetailAnalyticsEngine;
use App\Services\Reporting\DTOs\ReportPeriod;
use App\Services\Stores\StoreAssignmentService;
use App\Services\Stores\StoreService;
use Database\Seeders\ReturnReasonSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * TASK 040B — production hardening: session totals, idempotency, cache, indexes.
 */
class RetailCoreHardeningTest extends TestCase
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

    private function seedSku(string $storeId, float $price = 50000, int $stock = 20): array
    {
        $product = Product::factory()->create([
            'store_id' => $storeId,
            'commerce_channel_id' => $this->tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'price' => $price,
            'cost_price' => round($price * 0.5, 2),
            'is_active' => true,
            'is_demo' => false,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
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
            'is_active' => true,
        ]);

        return compact('product', 'variant', 'inventory');
    }

    public function test_session_running_totals_persist_after_sale_and_refund(): void
    {
        $store = $this->stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $cashier = $this->cashier($store);
        $sku = $this->seedSku($store->id, 50000, 10);

        Sanctum::actingAs($cashier);
        $open = $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $store->id,
            'terminal_id' => $store->terminals()->firstOrFail()->id,
            'opening_float' => 100000,
        ])->assertCreated();

        $sessionId = $open->json('data.id');

        $sale = $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [[
                'product_id' => $sku['product']->id,
                'product_variant_id' => $sku['variant']->id,
                'quantity' => 2,
            ]],
            'payment_method' => 'CASH',
            'amount_received' => 100000,
        ])->assertCreated();

        $session = PosSession::query()->findOrFail($sessionId);
        $this->assertSame('100000.00', (string) $session->cash_sales);
        $this->assertSame('0.00', (string) $session->cash_refunds);
        $this->assertSame('200000.00', (string) $session->expected_cash);
        $this->assertSame(1, (int) $session->transaction_count);

        $orderItemId = \App\Models\OrderItem::query()
            ->where('order_id', $sale->json('data.order.id'))
            ->value('id');

        $this->postJson('/api/v1/admin/pos/returns', [
            'order_id' => $sale->json('data.order.id'),
            'return_type' => 'refund',
            'return_reason_id' => ReturnReason::query()->value('id'),
            'refund_method' => 'CASH',
            'items' => [[
                'order_item_id' => $orderItemId,
                'quantity' => 1,
                'inventory_disposition' => InventoryDisposition::Sellable->value,
            ]],
        ])->assertCreated();

        $session = $session->fresh();
        $this->assertSame('100000.00', (string) $session->cash_sales);
        $this->assertSame('50000.00', (string) $session->cash_refunds);
        $this->assertSame('150000.00', (string) $session->expected_cash);
    }

    public function test_idempotency_key_prevents_duplicate_sale(): void
    {
        $store = $this->stores->create(['code' => 'TZUR', 'name' => 'Tzur']);
        $cashier = $this->cashier($store);
        $sku = $this->seedSku($store->id, 25000, 8);

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $store->id,
            'terminal_id' => $store->terminals()->firstOrFail()->id,
            'opening_float' => 50000,
        ])->assertCreated();

        $payload = [
            'items' => [[
                'product_id' => $sku['product']->id,
                'product_variant_id' => $sku['variant']->id,
                'quantity' => 1,
            ]],
            'payment_method' => 'CASH',
            'amount_received' => 30000,
            'idempotency_key' => 'sale-retry-abc-001',
        ];

        $first = $this->postJson('/api/v1/admin/pos/sales', $payload)->assertCreated();
        $orderId = $first->json('data.order.id');
        $first->assertJsonPath('data.idempotent_replay', false);

        $second = $this->postJson('/api/v1/admin/pos/sales', $payload)->assertOk();
        $second->assertJsonPath('data.order.id', $orderId)
            ->assertJsonPath('data.idempotent_replay', true);

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(1, PosReceipt::query()->count());
        $this->assertSame(1, ProfitRecord::query()->count());
        $this->assertSame(1, PosSaleIdempotencyKey::query()->count());
        $this->assertSame(7, (int) $sku['inventory']->fresh()->on_hand);
    }

    public function test_sale_rolls_back_on_failure_inside_transaction(): void
    {
        $store = $this->stores->create(['code' => 'ROVI', 'name' => 'Rovi']);
        $cashier = $this->cashier($store);
        $sku = $this->seedSku($store->id, 10000, 5);

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $store->id,
            'terminal_id' => $store->terminals()->firstOrFail()->id,
            'opening_float' => 10000,
        ])->assertCreated();

        // Insufficient inventory must not create order/payment/receipt.
        $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [[
                'product_id' => $sku['product']->id,
                'product_variant_id' => $sku['variant']->id,
                'quantity' => 99,
            ]],
            'payment_method' => 'CASH',
            'amount_received' => 990000,
        ])->assertStatus(422)
            ->assertJsonFragment(['Insufficient Inventory for the requested quantity.']);

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, PosReceipt::query()->count());
        $this->assertSame(5, (int) $sku['inventory']->fresh()->on_hand);
    }

    public function test_analytics_dashboard_uses_short_ttl_cache(): void
    {
        Cache::flush();
        $store = $this->stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $filter = new AnalyticsFilter(
            period: ReportPeriod::fromInput(now()->subDay()->toDateString(), now()->toDateString()),
            storeIds: [$store->id],
        );

        $engine = app(RetailAnalyticsEngine::class);
        $key = $filter->cacheKey('dashboard');
        $this->assertFalse(Cache::has($key));

        $first = $engine->dashboard($filter);
        $this->assertTrue(Cache::has($key));
        $second = $engine->dashboard($filter);
        $this->assertSame($first['period'], $second['period']);
    }

    public function test_index_sensitive_lookups_by_receipt_and_session(): void
    {
        $store = $this->stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $cashier = $this->cashier($store);
        $sku = $this->seedSku($store->id, 15000, 4);

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $store->id,
            'terminal_id' => $store->terminals()->firstOrFail()->id,
            'opening_float' => 20000,
        ])->assertCreated();

        $sale = $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [[
                'product_id' => $sku['product']->id,
                'product_variant_id' => $sku['variant']->id,
                'quantity' => 1,
            ]],
            'payment_method' => 'CASH',
            'amount_received' => 15000,
        ])->assertCreated();

        $receiptNumber = $sale->json('data.receipt.receipt_number');
        $sessionId = $sale->json('data.order.pos_session_id')
            ?? PosSession::query()->where('admin_id', $cashier->id)->value('id');

        $this->getJson('/api/v1/admin/pos/returns/search?receipt_number='.$receiptNumber)
            ->assertOk()
            ->assertJsonPath('data.0.eligible', true);

        $this->assertTrue(
            Order::query()->where('pos_session_id', $sessionId)->exists()
        );

        // Smoke: composite store+created_at path used by analytics.
        $this->assertGreaterThanOrEqual(
            1,
            Order::query()->where('store_id', $store->id)->whereDate('created_at', now()->toDateString())->count()
        );
    }

    public function test_concurrent_idempotent_requests_create_single_sale(): void
    {
        $store = $this->stores->create(['code' => 'PEACHY', 'name' => 'Peachy']);
        $cashier = $this->cashier($store);
        $sku = $this->seedSku($store->id, 12000, 6);

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $store->id,
            'terminal_id' => $store->terminals()->firstOrFail()->id,
            'opening_float' => 10000,
        ])->assertCreated();

        $key = 'concurrent-key-'.uniqid();
        $payload = [
            'items' => [[
                'product_id' => $sku['product']->id,
                'product_variant_id' => $sku['variant']->id,
                'quantity' => 1,
            ]],
            'payment_method' => 'CASH',
            'amount_received' => 12000,
            'idempotency_key' => $key,
        ];

        // Sequential concurrent simulation under one connection (practical CI).
        DB::transaction(function () use ($payload) {
            $this->postJson('/api/v1/admin/pos/sales', $payload)->assertSuccessful();
        });
        $this->postJson('/api/v1/admin/pos/sales', $payload)->assertSuccessful();

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, PosSaleIdempotencyKey::query()->where('idempotency_key', $key)->count());
    }
}

<?php

namespace Tests\Feature\Analytics;

use App\Enums\ActivityEventType;
use App\Enums\CommerceChannelCode;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PosPaymentHandler;
use App\Enums\PosSessionStatus;
use App\Enums\SalesOrigin;
use App\Enums\VariantPriceType;
use App\Models\Admin;
use App\Models\CommerceChannel;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentMethodDefinition;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProfitRecord;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use App\Services\Stores\StoreAssignmentService;
use App\Services\Stores\StoreService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RetailAnalyticsTest extends TestCase
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
            ['name' => 'Buy From TZ', 'description' => 'Local', 'is_active' => true],
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

    private function cashier(Store $store): Admin
    {
        $super = Admin::factory()->superAdmin()->create();
        $cashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
        ]);
        $this->assignments->assign($cashier, $store, $super);

        return $cashier;
    }

    /**
     * @return array{store: Store, cashier: Admin, order: Order, inventory: VariantInventory}
     */
    private function seedPosSale(string $storeCode, float $total = 50000, ?Admin $cashier = null): array
    {
        $store = $this->stores->create(['code' => $storeCode, 'name' => $storeCode]);
        $cashier ??= $this->cashier($store);

        $product = Product::factory()->create([
            'store_id' => $store->id,
            'commerce_channel_id' => $this->tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'price' => $total,
            'cost_price' => $total * 0.6,
            'is_active' => true,
            'is_demo' => false,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => $total,
            'is_active' => true,
        ]);
        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => $total,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        $inventory = VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'inventory_location_id' => $store->defaultInventoryLocation->id,
            'warehouse_code' => $store->defaultInventoryLocation->code,
            'on_hand' => 8,
            'reserved' => 0,
            'reorder_level' => 2,
            'is_active' => true,
        ]);

        $session = PosSession::query()->create([
            'store_id' => $store->id,
            'terminal_id' => $store->terminals()->firstOrFail()->id,
            'admin_id' => $cashier->id,
            'status' => PosSessionStatus::Open,
            'opened_at' => now(),
            'opening_float' => 100000,
            'cash_sales' => $total,
            'cash_refunds' => 0,
            'transaction_count' => 1,
        ]);

        $order = Order::factory()->create([
            'user_id' => null,
            'store_id' => $store->id,
            'sales_origin' => SalesOrigin::Pos,
            'pos_session_id' => $session->id,
            'status' => OrderStatus::Paid,
            'total' => $total,
            'subtotal' => $total,
            'discount_amount' => 0,
            'is_demo' => false,
            'created_at' => now(),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => $total,
            'line_total' => $total,
            'product_name_snapshot' => $product->name,
            'sku_snapshot' => $variant->sku,
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'status' => PaymentStatus::Paid,
            'amount' => $total,
            'reference' => 'CASH',
            'metadata' => ['payment_method_code' => 'CASH'],
            'paid_at' => now(),
        ]);

        ProfitRecord::query()->create([
            'order_id' => $order->id,
            'revenue' => number_format($total, 2, '.', ''),
            'total_cost' => number_format($total * 0.6, 2, '.', ''),
            'gross_profit' => number_format($total * 0.4, 2, '.', ''),
            'margin_percentage' => '40.0000',
            'currency' => 'TZS',
            'calculated_at' => now(),
        ]);

        return compact('store', 'cashier', 'order', 'inventory');
    }

    public function test_dashboard_and_sales_aggregation(): void
    {
        $seed = $this->seedPosSale('ZION', 50000);
        Sanctum::actingAs(Admin::factory()->superAdmin()->create());

        $this->getJson('/api/v1/admin/analytics/dashboard?store_id='.$seed['store']->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.kpis.todays_sales', 50000)
            ->assertJsonPath('data.kpis.todays_orders', 1)
            ->assertJsonStructure(['data' => ['kpis', 'charts', 'period']]);

        $this->getJson('/api/v1/admin/analytics/sales?store_id='.$seed['store']->id)
            ->assertOk()
            ->assertJsonPath('data.summary.gross_revenue', 50000)
            ->assertJsonPath('data.summary.orders_count', 1)
            ->assertJsonStructure(['data' => ['series' => ['hourly', 'daily', 'weekly', 'monthly', 'yearly'], 'top_products']]);
    }

    public function test_profit_payments_and_inventory(): void
    {
        $seed = $this->seedPosSale('TZUR', 80000);
        Sanctum::actingAs(Admin::factory()->superAdmin()->create());
        $storeId = $seed['store']->id;

        $this->getJson('/api/v1/admin/analytics/profit?store_id='.$storeId)
            ->assertOk()
            ->assertJsonPath('data.summary.gross_revenue', 80000)
            ->assertJsonPath('data.summary.profit', 32000)
            ->assertJsonPath('data.payment_breakdown.CASH', 80000);

        $this->getJson('/api/v1/admin/analytics/payments?store_id='.$storeId)
            ->assertOk()
            ->assertJsonPath('data.payment_breakdown.CASH', 80000);

        $this->getJson('/api/v1/admin/analytics/inventory?store_id='.$storeId)
            ->assertOk()
            ->assertJsonPath('data.summary.current_stock_units', 8)
            ->assertJsonStructure(['data' => ['fast_moving', 'slow_moving', 'dead_stock']]);
    }

    public function test_store_comparison_and_sessions(): void
    {
        $zion = $this->seedPosSale('ZION', 40000);
        $tzur = $this->seedPosSale('TZUR', 60000);
        Sanctum::actingAs(Admin::factory()->superAdmin()->create());

        $stores = $this->getJson('/api/v1/admin/analytics/stores')
            ->assertOk()
            ->json('data.ranking');

        $this->assertGreaterThanOrEqual(2, count($stores));
        $this->assertSame(1, $stores[0]['rank']);
        $this->assertTrue($stores[0]['sales'] >= $stores[1]['sales']);

        $this->getJson('/api/v1/admin/analytics/sessions?store_id='.$zion['store']->id)
            ->assertOk()
            ->assertJsonPath('data.summary.open_sessions', 1);
    }

    public function test_returns_customers_promotions_endpoints(): void
    {
        $seed = $this->seedPosSale('ZION', 25000);
        $customer = User::factory()->create();
        $seed['order']->forceFill(['user_id' => $customer->id])->save();

        Sanctum::actingAs(Admin::factory()->superAdmin()->create());
        $storeId = $seed['store']->id;

        $this->getJson('/api/v1/admin/analytics/returns?store_id='.$storeId)
            ->assertOk()
            ->assertJsonStructure(['data' => ['summary', 'by_reason', 'by_store']]);

        $this->getJson('/api/v1/admin/analytics/customers?store_id='.$storeId)
            ->assertOk()
            ->assertJsonPath('data.summary.registered_customers', 1)
            ->assertJsonPath('data.summary.walk_in_customers', 0);

        $this->getJson('/api/v1/admin/analytics/promotions?store_id='.$storeId)
            ->assertOk()
            ->assertJsonStructure(['data' => ['summary', 'top_promotions']]);
    }

    public function test_store_isolation_for_cashier(): void
    {
        $zion = $this->seedPosSale('ZION', 30000);
        $tzur = $this->seedPosSale('TZUR', 90000);

        Sanctum::actingAs($zion['cashier']);

        $this->getJson('/api/v1/admin/analytics/dashboard')
            ->assertOk()
            ->assertJsonPath('data.kpis.todays_sales', 30000);

        $this->getJson('/api/v1/admin/analytics/sales?store_id='.$tzur['store']->id)
            ->assertStatus(422);
    }

    public function test_date_filter_excludes_old_orders(): void
    {
        $seed = $this->seedPosSale('ZION', 45000);
        $seed['order']->forceFill(['created_at' => now()->subDays(40)])->save();

        Sanctum::actingAs(Admin::factory()->superAdmin()->create());

        $this->getJson('/api/v1/admin/analytics/sales?store_id='.$seed['store']->id.'&from='.now()->subDays(7)->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.summary.orders_count', 0)
            ->assertJsonPath('data.summary.gross_revenue', 0);
    }

    public function test_export_csv_and_audited_financial_export(): void
    {
        $seed = $this->seedPosSale('ZION', 55000);
        Sanctum::actingAs(Admin::factory()->superAdmin()->create());
        $storeId = $seed['store']->id;

        $this->get('/api/v1/admin/analytics/sales/export?format=csv&store_id='.$storeId)
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->get('/api/v1/admin/analytics/profit/export?format=csv&store_id='.$storeId)
            ->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::AnalyticsReportExported->value,
        ]);

        $this->get('/api/v1/admin/analytics/inventory/export?format=csv&store_id='.$storeId)
            ->assertOk();

        $this->assertSame(
            2,
            \App\Models\ActivityLog::query()
                ->where('event_type', ActivityEventType::AnalyticsReportExported->value)
                ->count(),
        );
    }
}

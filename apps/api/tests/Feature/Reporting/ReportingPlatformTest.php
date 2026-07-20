<?php

namespace Tests\Feature\Reporting;

use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\WarehouseJobStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\WarehouseJob;
use App\Services\Reporting\MetricsEngine;
use App\Services\Reporting\ReportingEngine;
use App\Services\Reporting\DTOs\ReportPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportingPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_sales_and_orders(): void
    {
        $user = User::factory()->create();
        Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'total' => 100000,
            'is_demo' => false,
            'created_at' => now(),
        ]);
        Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::PendingPayment,
            'total' => 50000,
            'is_demo' => false,
            'created_at' => now(),
        ]);
        Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Cancelled,
            'total' => 10000,
            'is_demo' => false,
            'created_at' => now(),
        ]);

        $metrics = app(MetricsEngine::class);
        $period = ReportPeriod::fromInput(now()->subDay()->toDateString(), now()->toDateString());
        $sales = $metrics->sales($period);
        $orders = $metrics->orders($period);

        $this->assertGreaterThanOrEqual(100000, $sales['paid_revenue']);
        $this->assertGreaterThanOrEqual(50000, $sales['pending_revenue']);
        $this->assertGreaterThanOrEqual(3, $orders['total_orders']);
        $this->assertGreaterThanOrEqual(1, $orders['cancelled_orders']);
    }

    public function test_dashboard_via_reporting_engine_only(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        Order::factory()->create([
            'status' => OrderStatus::Delivered,
            'total' => 25000,
            'is_demo' => false,
        ]);

        $response = $this->getJson('/api/v1/admin/dashboard');
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'sales' => ['total_revenue', 'paid_revenue', 'pending_revenue', 'refunded_revenue'],
                    'orders',
                    'customers',
                    'warehouse',
                    'shipments',
                    'returns',
                    'notifications',
                    'charts' => [
                        'daily_revenue',
                        'orders_trend',
                        'payment_status',
                        'warehouse_status',
                        'shipment_status',
                        'returns_trend',
                    ],
                    'top_products',
                    'recent_activity',
                ],
            ]);
    }

    public function test_reports_endpoints(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        foreach (['sales', 'orders', 'payments', 'warehouse', 'shipments', 'returns', 'notifications'] as $type) {
            $this->getJson("/api/v1/admin/reports/{$type}")
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.type', $type)
                ->assertJsonStructure(['data' => ['period', 'summary', 'rows', 'columns']]);
        }
    }

    public function test_export_csv_and_xlsx(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        Order::factory()->create(['status' => OrderStatus::Paid, 'is_demo' => false, 'total' => 1000]);

        $csv = $this->get('/api/v1/admin/reports/orders/export?format=csv');
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('content-type'));

        $xlsx = $this->get('/api/v1/admin/reports/orders/export?format=xlsx');
        $xlsx->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            (string) $xlsx->headers->get('content-type'),
        );
    }

    public function test_date_range_filtering(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        Order::factory()->create([
            'status' => OrderStatus::Paid,
            'is_demo' => false,
            'total' => 999,
            'created_at' => now()->subDays(40),
        ]);
        Order::factory()->create([
            'status' => OrderStatus::Paid,
            'is_demo' => false,
            'total' => 111,
            'created_at' => now()->subDay(),
        ]);

        $from = now()->subDays(7)->toDateString();
        $to = now()->toDateString();

        $report = $this->getJson("/api/v1/admin/reports/orders?from={$from}&to={$to}")
            ->assertOk()
            ->json('data');

        $this->assertSame($from, $report['period']['from']);
        $this->assertSame($to, $report['period']['to']);
        $this->assertNotEmpty($report['columns']);
        $this->assertGreaterThanOrEqual(1, (int) ($report['summary']['total_orders'] ?? 0));
    }

    public function test_authorization_guest_and_customer_rejected(): void
    {
        $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
        $this->getJson('/api/v1/admin/reports/sales')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
        $this->getJson('/api/v1/admin/reports/sales')->assertUnauthorized();
    }

    public function test_warehouse_and_payment_metrics(): void
    {
        WarehouseJob::factory()->create(['status' => WarehouseJobStatus::Picking]);
        WarehouseJob::factory()->create(['status' => WarehouseJobStatus::Packing]);
        WarehouseJob::factory()->create(['status' => WarehouseJobStatus::ReadyToShip]);

        PaymentTransaction::factory()->create([
            'status' => PaymentTransactionStatus::Successful,
            'amount' => 5000,
        ]);

        $metrics = app(MetricsEngine::class);
        $warehouse = $metrics->warehouse();
        $this->assertGreaterThanOrEqual(1, $warehouse['picking']);
        $this->assertGreaterThanOrEqual(1, $warehouse['packing']);
        $this->assertGreaterThanOrEqual(1, $warehouse['ready_to_ship']);

        $payments = $metrics->paymentStatusBreakdown();
        $this->assertNotEmpty($payments);
    }

    public function test_reporting_engine_is_read_only_source(): void
    {
        $engine = app(ReportingEngine::class);
        $before = Order::query()->count();
        $engine->dashboard();
        $engine->report('orders');
        $this->assertSame($before, Order::query()->count());
    }

    public function test_unknown_export_format_rejected(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $this->getJson('/api/v1/admin/reports/orders/export?format=pdf')
            ->assertStatus(422);
    }
}

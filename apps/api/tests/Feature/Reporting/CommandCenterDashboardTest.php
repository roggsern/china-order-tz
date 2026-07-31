<?php

namespace Tests\Feature\Reporting;

use App\Enums\ChinaQcStatus;
use App\Enums\ChinaWorkflowStage;
use App\Enums\DeliveryType;
use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStrategy;
use App\Enums\LastMileReceivingMethod;
use App\Enums\OrderStatus;
use App\Enums\ShipmentLifecycleStatus;
use App\Models\Admin;
use App\Models\ChinaWorkflowRecord;
use App\Models\DeliveryOption;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\User;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Services\Reporting\CommandCenterDashboardService;
use App\Services\Reporting\DTOs\ReportPeriod;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommandCenterDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_dashboard_includes_command_center_sections(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'overview' => [
                        'orders_today',
                        'revenue_today',
                        'paid_orders_today',
                        'pending_actions',
                        'customers_total',
                        'new_customers',
                    ],
                    'operations' => [
                        'fulfillment_queue' => ['total', 'china', 'local'],
                        'warehouse',
                        'shipments',
                        'open_returns',
                    ],
                    'china_pipeline',
                    'tz_local',
                    'attention_items',
                    'store_summary' => ['active_stores', 'orders_today_by_store'],
                ],
            ]);
    }

    public function test_overview_aggregates_business_metrics(): void
    {
        $user = User::factory()->create();
        Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'total' => 150000,
            'is_demo' => false,
            'created_at' => now(),
        ]);

        $service = app(CommandCenterDashboardService::class);
        $overview = $service->build(ReportPeriod::fromInput(null, null, 30))['overview'];

        $this->assertGreaterThanOrEqual(1, $overview['orders_today']);
        $this->assertGreaterThanOrEqual(150000, $overview['revenue_today']);
        $this->assertGreaterThanOrEqual(1, $overview['paid_orders_today']);
        $this->assertIsInt($overview['pending_actions']);
    }

    public function test_fulfillment_queue_counts_active_only(): void
    {
        $local = $this->createLocalFulfillment(FulfillmentStatus::Processing);
        $china = $this->createChinaFulfillment(FulfillmentStatus::Pending);
        $done = $this->createLocalFulfillment(FulfillmentStatus::Delivered);

        $operations = app(CommandCenterDashboardService::class)
            ->build(ReportPeriod::fromInput(null, null, 30))['operations'];

        $this->assertSame(2, $operations['fulfillment_queue']['total']);
        $this->assertSame(1, $operations['fulfillment_queue']['china']);
        $this->assertSame(1, $operations['fulfillment_queue']['local']);
        $this->assertNotSame($done->id, $local->id);
    }

    public function test_china_pipeline_counts(): void
    {
        $procurement = $this->createChinaFulfillment(FulfillmentStatus::Pending, [
            'stage' => ChinaWorkflowStage::ProcurementInProgress,
        ]);
        $qcPending = $this->createChinaFulfillment(FulfillmentStatus::Processing, [
            'stage' => ChinaWorkflowStage::QcPending,
            'qc_status' => ChinaQcStatus::Pending,
        ]);
        $awaitingChoice = $this->createCompanyShippingShippedFulfillment(arrived: true, receivingMethod: null);
        $handoverPending = $this->createCompanyShippingShippedFulfillment(
            arrived: true,
            receivingMethod: LastMileReceivingMethod::SelfPickup,
        );

        $pipeline = app(CommandCenterDashboardService::class)
            ->build(ReportPeriod::fromInput(null, null, 30))['china_pipeline'];

        $this->assertGreaterThanOrEqual(1, $pipeline['procurement']);
        $this->assertGreaterThanOrEqual(1, $pipeline['qc_pending']);
        $this->assertGreaterThanOrEqual(1, $pipeline['awaiting_receiving_choice']);
        $this->assertGreaterThanOrEqual(1, $pipeline['handover_pending']);
        $this->assertNotSame($procurement->id, $qcPending->id);
        $this->assertNotSame($awaitingChoice->id, $handoverPending->id);
    }

    public function test_tz_local_pipeline_counts(): void
    {
        $this->createLocalFulfillment(FulfillmentStatus::Pending);
        $this->createLocalFulfillment(FulfillmentStatus::Processing);
        $this->createLocalFulfillment(FulfillmentStatus::ReadyForShipping);
        $this->createLocalFulfillment(FulfillmentStatus::Shipped);

        $pipeline = app(CommandCenterDashboardService::class)
            ->build(ReportPeriod::fromInput(null, null, 30))['tz_local'];

        $this->assertSame(1, $pipeline['pending']);
        $this->assertSame(1, $pipeline['processing']);
        $this->assertSame(1, $pipeline['ready_for_shipping']);
        $this->assertSame(1, $pipeline['shipped']);
        $this->assertSame($pipeline['ready_for_shipping'], $pipeline['ready_for_completion']);
    }

    public function test_attention_items_include_operational_alerts(): void
    {
        $stuck = $this->createLocalFulfillment(FulfillmentStatus::Processing);
        $stuck->forceFill(['updated_at' => now()->subDays(10)])->save();

        $this->createChinaFulfillment(FulfillmentStatus::Processing, [
            'stage' => ChinaWorkflowStage::QcPending,
            'qc_status' => ChinaQcStatus::Pending,
        ]);
        $this->createCompanyShippingShippedFulfillment(arrived: true, receivingMethod: null);
        $this->createCompanyShippingShippedFulfillment(
            arrived: true,
            receivingMethod: LastMileReceivingMethod::NegotiatedDelivery,
        );

        $attention = app(CommandCenterDashboardService::class)
            ->build(ReportPeriod::fromInput(null, null, 30))['attention_items'];

        $keys = collect($attention)->pluck('key')->all();
        $this->assertContains('stuck_fulfillment', $keys);
        $this->assertContains('pending_qc', $keys);
        $this->assertContains('awaiting_receiving_choice', $keys);
        $this->assertContains('pending_handover', $keys);
        $this->assertContains('open_returns', $keys);

        $stuckItem = collect($attention)->firstWhere('key', 'stuck_fulfillment');
        $this->assertGreaterThanOrEqual(1, $stuckItem['count']);
    }

    public function test_store_summary_groups_orders_today(): void
    {
        $store = Store::query()->create([
            'code' => 'TZ-001',
            'name' => 'Dar Store',
            'slug' => 'dar-store',
            'is_active' => true,
        ]);

        Order::factory()->create([
            'store_id' => $store->id,
            'status' => OrderStatus::Paid,
            'is_demo' => false,
            'created_at' => now(),
        ]);

        $summary = app(CommandCenterDashboardService::class)
            ->build(ReportPeriod::fromInput(null, null, 30))['store_summary'];

        $this->assertGreaterThanOrEqual(1, $summary['active_stores']);
        $this->assertNotEmpty($summary['orders_today_by_store']);
        $this->assertSame('Dar Store', $summary['orders_today_by_store'][0]['store_name']);
    }

    public function test_dashboard_requires_reports_view_permission(): void
    {
        Sanctum::actingAs(
            Admin::factory()->ordinary()->withPermissions([AdminPermissions::ORDERS_VIEW])->create(),
        );

        $this->getJson('/api/v1/admin/dashboard')->assertForbidden();
    }

    private function createLocalFulfillment(FulfillmentStatus $status): Fulfillment
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['fulfillment_source' => 'buy_from_tz']);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
            'is_demo' => false,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
        DeliveryOption::factory()->create([
            'order_id' => $order->id,
            'delivery_type' => DeliveryType::SelfPickup,
        ]);

        $fulfillment = app(FulfillmentEngine::class)
            ->createForOrder($order->fresh(['items.product']));

        $fulfillment->forceFill([
            'strategy' => FulfillmentStrategy::Local,
            'status' => $status,
        ])->save();

        return $fulfillment->fresh();
    }

    /**
     * @param  array<string, mixed>  $workflowAttributes
     */
    private function createChinaFulfillment(FulfillmentStatus $status, array $workflowAttributes = []): Fulfillment
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['fulfillment_source' => 'order_from_china']);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
            'is_demo' => false,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
        DeliveryOption::factory()->create([
            'order_id' => $order->id,
            'delivery_type' => DeliveryType::CompanyShipping,
        ]);

        $fulfillment = app(FulfillmentEngine::class)
            ->createForOrder($order->fresh(['items.product']));

        $fulfillment->forceFill([
            'strategy' => FulfillmentStrategy::China,
            'status' => $status,
        ])->save();

        $record = ChinaWorkflowRecord::query()->where('order_id', $order->id)->first();
        if ($record === null) {
            ChinaWorkflowRecord::query()->create(array_merge([
                'order_id' => $order->id,
                'fulfillment_id' => $fulfillment->id,
                'stage' => ChinaWorkflowStage::AwaitingProcurement,
                'qc_status' => ChinaQcStatus::Pending,
                'metadata' => [],
            ], $workflowAttributes));
        } else {
            $record->forceFill(array_merge(['fulfillment_id' => $fulfillment->id], $workflowAttributes))->save();
        }

        return $fulfillment->fresh(['chinaWorkflowRecord', 'shipment', 'order.deliveryOption']);
    }

    private function createCompanyShippingShippedFulfillment(
        bool $arrived,
        ?LastMileReceivingMethod $receivingMethod,
    ): Fulfillment {
        $fulfillment = $this->createChinaFulfillment(FulfillmentStatus::Shipped);
        $fulfillment->order?->deliveryOption?->forceFill([
            'last_mile_receiving_method' => $receivingMethod,
        ])->save();

        Shipment::factory()->create([
            'order_id' => $fulfillment->order_id,
            'fulfillment_id' => $fulfillment->id,
            'status' => $arrived ? ShipmentLifecycleStatus::Arrived : ShipmentLifecycleStatus::InTransit,
            'arrived_at' => $arrived ? now() : null,
        ]);

        return $fulfillment->fresh(['shipment', 'order.deliveryOption']);
    }
}

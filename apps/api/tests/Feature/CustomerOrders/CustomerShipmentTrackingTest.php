<?php

namespace Tests\Feature\CustomerOrders;

use App\Enums\CustomerOrderProgressKey;
use App\Enums\DeliveryOptionStatus;
use App\Enums\DeliveryType;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\DeliveryOption;
use App\Models\Fulfillment;
use App\Models\Payment;
use App\Enums\TimelineVisibility;
use App\Models\Admin;
use App\Models\Order;
use App\Models\ShipmentStatusHistory;
use App\Models\User;
use App\Services\Tracking\TrackingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerShipmentTrackingTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const FORBIDDEN_CUSTOMER_PHRASES = [
        'supplier',
        'procurement',
        'quality inspection',
        'quality check',
        'china warehouse',
        'export ready',
        'consolidation',
        'purchased from supplier',
        'supplier processing',
        'customs clearance',
        'arrived at china',
        'customer pickup',
        'click-and-collect',
        'agent arrived',
        'customer handover',
        'collection workflow',
        'pickup authorization',
    ];

    /** @var list<string> */
    private const ALLOWED_PROGRESS_LABELS = [
        'Order confirmed',
        'Preparing your order',
        'Ready to ship',
        'Shipped',
        'Delivered',
    ];

    /** @var list<string> */
    private const ALLOWED_COMPANY_SHIPPING_PROGRESS_LABELS = [
        'Order confirmed',
        'Preparing your order',
        'Shipped',
        'Arrived in Tanzania',
        'Choose receiving method',
        'Completed',
    ];

    /** @var list<string> */
    private const ALLOWED_AGENT_PROGRESS_LABELS = [
        'Order confirmed',
        'Preparing your order',
        'Sent to your agent',
        'Delivered to your agent',
    ];

    public function test_authenticated_customer_can_view_shipment_tracking(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/orders/{$order->id}/tracking");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.source', 'customer_progress');
    }

    public function test_customer_cannot_view_another_customers_shipment(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/orders/{$order->id}/tracking")->assertNotFound();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $order = Order::factory()->create();

        $this->getJson("/api/v1/orders/{$order->id}/tracking")->assertUnauthorized();
    }

    public function test_admin_token_rejected_on_customer_shipment_tracking(): void
    {
        $order = Order::factory()->create();

        Sanctum::actingAs(Admin::factory()->create());

        $this->getJson("/api/v1/orders/{$order->id}/tracking")->assertUnauthorized();
    }

    public function test_timeline_uses_progress_projection_not_legacy_shipment_status(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Processing,
            'shipment_status' => ShipmentStatus::SupplierProcessing,
        ]);

        ShipmentStatusHistory::query()->create([
            'order_id' => $order->id,
            'previous_status' => ShipmentStatus::PaymentConfirmed->value,
            'new_status' => ShipmentStatus::SupplierProcessing->value,
            'source' => 'china-supplier-proc:'.$order->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/orders/{$order->id}/tracking");

        $response->assertOk()
            ->assertJsonCount(5, 'data.timeline')
            ->assertJsonPath('data.progress.current_key', CustomerOrderProgressKey::Preparing->value)
            ->assertJsonPath('data.current_status', CustomerOrderProgressKey::Preparing->value)
            ->assertJsonPath('data.current_status_label', 'Preparing your order')
            ->assertJsonStructure([
                'data' => [
                    'order_number',
                    'current_status',
                    'current_status_label',
                    'progress' => [
                        'current_key',
                        'current_label',
                        'steps' => [
                            ['key', 'label', 'completed'],
                        ],
                    ],
                    'timeline' => [
                        ['key', 'step', 'completed', 'description'],
                    ],
                ],
            ]);

        $this->assertCustomerPayloadContainsNoInternalWorkflowLanguage($response->json('data'));
    }

    public function test_current_status_uses_progress_key_not_internal_shipment_status(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Delivered,
            'shipment_status' => ShipmentStatus::Delivered,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/orders/{$order->id}/tracking");

        $response->assertOk()
            ->assertJsonPath('data.current_status', CustomerOrderProgressKey::Delivered->value)
            ->assertJsonPath('data.current_status_label', 'Delivered');
    }

    public function test_timeline_labels_match_customer_journey_only(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/orders/{$order->id}/tracking");
        $response->assertOk();

        $labels = collect($response->json('data.timeline'))->pluck('step')->all();
        foreach ($labels as $label) {
            $this->assertContains($label, self::ALLOWED_PROGRESS_LABELS);
        }
    }

    public function test_customer_agent_order_returns_agent_delivery_progress_projection(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Processing,
            'shipment_status' => ShipmentStatus::SupplierProcessing,
        ]);

        Payment::factory()->paid()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);

        DeliveryOption::query()->create([
            'order_id' => $order->id,
            'delivery_type' => DeliveryType::CustomerAgent,
            'delivery_status' => DeliveryOptionStatus::Pending,
            'agent_name' => 'Jane Agent',
            'agent_contact' => '+255700000001',
        ]);

        Fulfillment::factory()->processing()->create([
            'order_id' => $order->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/orders/{$order->id}/tracking");
        $response->assertOk()
            ->assertJsonCount(4, 'data.timeline')
            ->assertJsonPath('data.progress.current_key', CustomerOrderProgressKey::Preparing->value)
            ->assertJsonPath('data.current_status_label', 'Preparing your order');

        $labels = collect($response->json('data.timeline'))->pluck('step')->all();
        $this->assertSame(self::ALLOWED_AGENT_PROGRESS_LABELS, $labels);

        $stepKeys = collect($response->json('data.progress.steps'))->pluck('key')->all();
        $this->assertSame([
            CustomerOrderProgressKey::OrderConfirmed->value,
            CustomerOrderProgressKey::Preparing->value,
            CustomerOrderProgressKey::SentToAgent->value,
            CustomerOrderProgressKey::DeliveredToAgent->value,
        ], $stepKeys);

        $this->assertCustomerPayloadContainsNoInternalWorkflowLanguage($response->json('data'));
    }

    public function test_company_shipping_order_keeps_standard_shipping_progress(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);

        DeliveryOption::query()->create([
            'order_id' => $order->id,
            'delivery_type' => DeliveryType::CompanyShipping,
            'delivery_status' => DeliveryOptionStatus::Pending,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/orders/{$order->id}/tracking");
        $response->assertOk()
            ->assertJsonCount(6, 'data.timeline');

        $labels = collect($response->json('data.timeline'))->pluck('step')->all();
        foreach ($labels as $label) {
            $this->assertContains($label, self::ALLOWED_COMPANY_SHIPPING_PROGRESS_LABELS);
        }

        $stepKeys = collect($response->json('data.progress.steps'))->pluck('key')->all();
        $this->assertSame([
            CustomerOrderProgressKey::OrderConfirmed->value,
            CustomerOrderProgressKey::Preparing->value,
            CustomerOrderProgressKey::Shipped->value,
            CustomerOrderProgressKey::ArrivedTanzania->value,
            CustomerOrderProgressKey::ChooseReceivingMethod->value,
            CustomerOrderProgressKey::Delivered->value,
        ], $stepKeys);

        $this->assertCustomerPayloadContainsNoInternalWorkflowLanguage($response->json('data'));
    }

    public function test_unified_timeline_is_sanitized_for_customers(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Processing,
            'shipment_status' => ShipmentStatus::QualityInspection,
        ]);

        $recordId = (string) \Illuminate\Support\Str::uuid();
        \Illuminate\Support\Facades\DB::table('china_workflow_records')->insert([
            'id' => $recordId,
            'order_id' => $order->id,
            'fulfillment_id' => null,
            'stage' => 'qc_pending',
            'qc_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('china_workflow_histories')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'china_workflow_record_id' => $recordId,
            'order_id' => $order->id,
            'admin_id' => null,
            'action' => 'procurement_started',
            'from_stage' => 'awaiting_procurement',
            'to_stage' => 'procurement_in_progress',
            'reason' => 'Purchase orders generated',
            'metadata' => null,
            'idempotency_key' => 'china-proc:'.$order->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/orders/{$order->id}/tracking");
        $response->assertOk();

        $this->assertCustomerPayloadContainsNoInternalWorkflowLanguage($response->json('data'));
        $this->assertSame(
            collect($response->json('data.timeline'))->pluck('key')->map(fn ($key) => strtolower((string) $key))->all(),
            collect($response->json('data.unified_timeline'))->pluck('code')->all(),
        );
    }

    public function test_admin_internal_timeline_still_exposes_operational_events(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::Processing,
            'shipment_status' => ShipmentStatus::SupplierProcessing,
        ]);

        ShipmentStatusHistory::query()->create([
            'order_id' => $order->id,
            'previous_status' => ShipmentStatus::PaymentConfirmed->value,
            'new_status' => ShipmentStatus::SupplierProcessing->value,
            'source' => 'china-supplier-proc:'.$order->id,
        ]);

        $recordId = (string) \Illuminate\Support\Str::uuid();
        \Illuminate\Support\Facades\DB::table('china_workflow_records')->insert([
            'id' => $recordId,
            'order_id' => $order->id,
            'fulfillment_id' => null,
            'stage' => 'procurement_in_progress',
            'qc_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('china_workflow_histories')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'china_workflow_record_id' => $recordId,
            'order_id' => $order->id,
            'admin_id' => null,
            'action' => 'procurement_started',
            'from_stage' => 'awaiting_procurement',
            'to_stage' => 'procurement_in_progress',
            'reason' => 'Purchase orders generated',
            'metadata' => null,
            'idempotency_key' => 'china-proc:'.$order->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs(Admin::factory()->superAdmin()->create());

        $internal = app(TrackingEngine::class)->composeOrderTimeline($order->fresh(), TimelineVisibility::Internal);
        $customer = app(TrackingEngine::class)->composeOrderTimeline($order->fresh(), TimelineVisibility::Customer);

        $internalCodes = collect($internal['timeline'])->pluck('code')->all();
        $customerCodes = collect($customer['timeline'])->pluck('code')->all();

        $this->assertContains('journey_supplier_processing', $internalCodes);
        $this->assertContains('procurement_started', $internalCodes);
        $this->assertNotContains('journey_supplier_processing', $customerCodes);
        $this->assertNotContains('procurement_started', $customerCodes);
        $this->assertTrue(count($internal['timeline']) >= count($customer['timeline']));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertCustomerPayloadContainsNoInternalWorkflowLanguage(array $payload): void
    {
        $haystack = strtolower(json_encode($payload) ?: '');

        foreach (self::FORBIDDEN_CUSTOMER_PHRASES as $phrase) {
            $this->assertStringNotContainsString(
                strtolower($phrase),
                $haystack,
                "Customer tracking leaked internal phrase [{$phrase}].",
            );
        }
    }
}

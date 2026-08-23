<?php

namespace Tests\Unit\Services\Orders;

use App\Enums\CustomerOrderProgressKey;
use App\Enums\DeliveryOptionStatus;
use App\Enums\DeliveryType;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\PaymentTransaction;
use App\Enums\ShipmentLifecycleStatus;
use App\Enums\ShipmentStatus;
use App\Models\DeliveryOption;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Orders\CustomerOrderProgressResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrderProgressResolverTest extends TestCase
{
    use RefreshDatabase;

    private CustomerOrderProgressResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(CustomerOrderProgressResolver::class);
    }

    public function test_pending_payment_order_projects_awaiting_payment(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::PendingPayment,
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'status' => PaymentStatus::Pending,
        ]);

        $progress = $this->resolver->resolve($order->fresh(['payments']));

        $this->assertSame('AWAITING_PAYMENT', $progress['current_key']);
        $this->assertSame('Awaiting payment', $progress['current_label']);
        $this->assertFalse(collect($progress['steps'])->contains(fn (array $step) => $step['completed']));
    }

    public function test_cancelled_order_is_not_awaiting_payment_when_stale_transaction_is_processing(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Cancelled,
            'paid_at' => null,
        ]);

        PaymentTransaction::factory()->processing()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
            'status' => PaymentTransactionStatus::Processing,
        ]);

        $progress = $this->resolver->resolve($order->fresh(['payments', 'paymentTransactions']));

        $this->assertSame(CustomerOrderProgressKey::Cancelled->value, $progress['current_key']);
        $this->assertSame('Order cancelled', $progress['current_label']);
    }

    public function test_paid_order_projects_order_confirmed(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
        ]);

        Payment::factory()->paid()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);

        $progress = $this->resolver->resolve($order->fresh(['payments']));

        $this->assertSame('ORDER_CONFIRMED', $progress['current_key']);
        $this->assertSame('Order confirmed', $progress['current_label']);
    }

    public function test_processing_fulfilment_projects_preparing(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Processing,
        ]);

        Payment::factory()->paid()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);

        Fulfillment::factory()->processing()->create([
            'order_id' => $order->id,
        ]);

        $progress = $this->resolver->resolve($order->fresh(['payments', 'fulfillment']));

        $this->assertSame('PREPARING', $progress['current_key']);
        $this->assertSame('Preparing your order', $progress['current_label']);
        $this->assertTrue($progress['steps'][0]['completed']);
        $this->assertTrue($progress['steps'][1]['completed']);
        $this->assertFalse($progress['steps'][2]['completed']);
    }

    public function test_ready_for_shipping_fulfilment_projects_ready_to_ship(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Processing,
        ]);

        Payment::factory()->paid()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);

        Fulfillment::factory()->create([
            'order_id' => $order->id,
            'status' => FulfillmentStatus::ReadyForShipping,
        ]);

        $progress = $this->resolver->resolve($order->fresh(['payments', 'fulfillment']));

        $this->assertSame('READY_TO_SHIP', $progress['current_key']);
        $this->assertSame('Ready to ship', $progress['current_label']);
    }

    public function test_shipped_order_projects_shipped(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Shipped,
        ]);

        Payment::factory()->paid()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);

        Fulfillment::factory()->create([
            'order_id' => $order->id,
            'status' => FulfillmentStatus::Shipped,
        ]);

        $progress = $this->resolver->resolve($order->fresh(['payments', 'fulfillment']));

        $this->assertSame('SHIPPED', $progress['current_key']);
        $this->assertSame('Shipped', $progress['current_label']);
    }

    public function test_delivered_order_projects_delivered(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Delivered,
        ]);

        Payment::factory()->paid()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);

        Fulfillment::factory()->create([
            'order_id' => $order->id,
            'status' => FulfillmentStatus::Delivered,
        ]);

        $progress = $this->resolver->resolve($order->fresh(['payments', 'fulfillment']));

        $this->assertSame('DELIVERED', $progress['current_key']);
        $this->assertSame('Delivered', $progress['current_label']);
        $this->assertTrue($progress['steps'][3]['completed']);
        $this->assertTrue($progress['steps'][4]['completed']);
    }

    public function test_china_shipment_status_maps_to_customer_journey(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Processing,
            'shipment_status' => ShipmentStatus::QualityInspection,
        ]);

        Payment::factory()->paid()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);

        $progress = $this->resolver->resolve($order->fresh(['payments']));

        $this->assertSame('PREPARING', $progress['current_key']);

        $order->update(['shipment_status' => ShipmentStatus::CustomsClearance]);
        $progress = $this->resolver->resolve($order->fresh(['payments']));

        $this->assertSame('SHIPPED', $progress['current_key']);
    }

    public function test_operational_shipment_status_enriches_projection(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Processing,
        ]);

        Payment::factory()->paid()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);

        $fulfillment = Fulfillment::factory()->create([
            'order_id' => $order->id,
            'status' => FulfillmentStatus::ReadyForShipping,
        ]);

        Shipment::factory()->create([
            'order_id' => $order->id,
            'fulfillment_id' => $fulfillment->id,
            'status' => ShipmentLifecycleStatus::InTransit,
        ]);

        $progress = $this->resolver->resolve($order->fresh(['payments', 'fulfillment', 'shipments']));

        $this->assertSame('SHIPPED', $progress['current_key']);
    }

    public function test_progress_steps_include_canonical_journey(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::Paid,
        ]);

        Payment::factory()->paid()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
        ]);

        $progress = $this->resolver->resolve($order->fresh(['payments']));

        $this->assertSame([
            'ORDER_CONFIRMED',
            'PREPARING',
            'READY_TO_SHIP',
            'SHIPPED',
            'DELIVERED',
        ], array_column($progress['steps'], 'key'));
    }

    public function test_customer_agent_order_projects_agent_delivery_journey(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Processing,
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

        $progress = $this->resolver->resolve($order->fresh(['payments', 'fulfillment', 'deliveryOption']));

        $this->assertSame('PREPARING', $progress['current_key']);
        $this->assertSame([
            'ORDER_CONFIRMED',
            'PREPARING',
            'SENT_TO_AGENT',
            'DELIVERED_TO_AGENT',
        ], array_column($progress['steps'], 'key'));
        $this->assertSame('Sent to your agent', $progress['steps'][2]['label']);
        $this->assertSame('Delivered to your agent', $progress['steps'][3]['label']);
    }

    public function test_customer_agent_delivered_fulfilment_projects_delivered_to_agent(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Delivered,
        ]);

        Payment::factory()->paid()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);

        DeliveryOption::query()->create([
            'order_id' => $order->id,
            'delivery_type' => DeliveryType::CustomerAgent,
            'delivery_status' => DeliveryOptionStatus::Completed,
            'agent_name' => 'Jane Agent',
            'agent_contact' => '+255700000001',
        ]);

        Fulfillment::factory()->create([
            'order_id' => $order->id,
            'status' => FulfillmentStatus::Delivered,
        ]);

        $progress = $this->resolver->resolve($order->fresh(['payments', 'fulfillment', 'deliveryOption']));

        $this->assertSame(CustomerOrderProgressKey::DeliveredToAgent->value, $progress['current_key']);
        $this->assertSame('Delivered to your agent', $progress['current_label']);
    }

    public function test_tz_local_self_pickup_projects_manual_logistics_journey(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Processing,
        ]);

        Payment::factory()->paid()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);

        DeliveryOption::query()->create([
            'order_id' => $order->id,
            'delivery_type' => DeliveryType::SelfPickup,
            'delivery_status' => DeliveryOptionStatus::Pending,
        ]);

        Fulfillment::factory()->create([
            'order_id' => $order->id,
            'status' => FulfillmentStatus::ReadyForShipping,
        ]);

        $progress = $this->resolver->resolve($order->fresh(['payments', 'fulfillment', 'deliveryOption']));

        $this->assertSame('READY_TO_SHIP', $progress['current_key']);
        $this->assertSame('Order ready', $progress['current_label']);
        $this->assertSame([
            'ORDER_CONFIRMED',
            'PREPARING',
            'READY_TO_SHIP',
            'DELIVERED',
        ], array_column($progress['steps'], 'key'));
        $this->assertSame('Completed', $progress['steps'][3]['label']);
    }

    public function test_tz_local_delivery_arrangement_projects_manual_logistics_journey(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Delivered,
        ]);

        Payment::factory()->paid()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);

        DeliveryOption::query()->create([
            'order_id' => $order->id,
            'delivery_type' => DeliveryType::NegotiatedDelivery,
            'delivery_status' => DeliveryOptionStatus::Completed,
        ]);

        Fulfillment::factory()->create([
            'order_id' => $order->id,
            'status' => FulfillmentStatus::Delivered,
        ]);

        $progress = $this->resolver->resolve($order->fresh(['payments', 'fulfillment', 'deliveryOption']));

        $this->assertSame('DELIVERED', $progress['current_key']);
        $this->assertSame('Completed', $progress['current_label']);
        $this->assertTrue($progress['steps'][3]['completed']);
    }

    public function test_company_shipping_order_keeps_shipping_journey_after_agent_enum_addition(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Processing,
        ]);

        Payment::factory()->paid()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);

        DeliveryOption::query()->create([
            'order_id' => $order->id,
            'delivery_type' => DeliveryType::CompanyShipping,
            'delivery_status' => DeliveryOptionStatus::Pending,
            'shipping_method' => 'air',
        ]);

        Fulfillment::factory()->create([
            'order_id' => $order->id,
            'status' => FulfillmentStatus::ReadyForShipping,
        ]);

        $progress = $this->resolver->resolve($order->fresh(['payments', 'fulfillment', 'deliveryOption']));

        $this->assertSame('PREPARING', $progress['current_key']);
        $this->assertSame([
            'ORDER_CONFIRMED',
            'PREPARING',
            'SHIPPED',
            'ARRIVED_TANZANIA',
            'CHOOSE_RECEIVING_METHOD',
            'DELIVERED',
        ], array_column($progress['steps'], 'key'));
    }
}

<?php

namespace Tests\Unit\Shipments;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Shipments\ShipmentTimelineBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentTimelineBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_builder_generates_all_timeline_steps(): void
    {
        $order = Order::factory()->create();
        $builder = new ShipmentTimelineBuilder;

        $timeline = $builder->build(ShipmentStatus::OrderReceived, $order);

        $this->assertCount(count(ShipmentStatus::timeline()), $timeline);
    }

    public function test_builder_marks_completed_steps_up_to_current_status(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);

        $builder = new ShipmentTimelineBuilder;
        $timeline = $builder->build(ShipmentStatus::PaymentConfirmed, $order);

        $this->assertTrue($timeline[0]['completed']);
        $this->assertTrue($timeline[1]['completed']);
        $this->assertFalse($timeline[2]['completed']);
        $this->assertSame('Order Received', $timeline[0]['step']);
        $this->assertSame('Payment Confirmed', $timeline[1]['step']);
        $this->assertNotEmpty($timeline[0]['description']);
    }

    public function test_builder_marks_all_steps_completed_for_delivered_status(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::Delivered,
        ]);

        $builder = new ShipmentTimelineBuilder;
        $timeline = $builder->build(ShipmentStatus::Delivered, $order);

        foreach ($timeline as $step) {
            $this->assertTrue($step['completed']);
            $this->assertNotEmpty($step['description']);
        }
    }
}

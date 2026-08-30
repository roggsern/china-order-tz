<?php

namespace Tests\Unit\Notifications\WhatsApp;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Models\Notification;
use App\Services\Notifications\WhatsApp\GhalaWhatsAppTemplateMapper;
use Tests\TestCase;

class GhalaWhatsAppTemplateMapperTest extends TestCase
{
    public function test_all_eight_templates_have_exact_name_language_order_and_count(): void
    {
        $mapper = app(GhalaWhatsAppTemplateMapper::class);

        $this->assertSame([
            'order_created',
            'payment_confirmed',
            'order_processing',
            'shipment_arrived_tanzania',
            'warehouse_ready_for_pickup',
            'shipment_created',
            'order_delivered',
            'order_cancelled',
        ], GhalaWhatsAppTemplateMapper::supportedEventTypes());

        $cases = [
            [NotificationEventType::OrderCreated, 'order_confirmation', ['Asha', 'ORD-1', '1000.00 TZS']],
            [NotificationEventType::PaymentConfirmed, 'payment_received', ['Asha', '1000.00 TZS', 'ORD-1']],
            [NotificationEventType::OrderProcessing, 'order_processing', ['Asha', 'ORD-1']],
            [NotificationEventType::ShipmentArrivedTanzania, 'order_arrived_tanzania', ['Asha', 'ORD-1']],
            [NotificationEventType::WarehouseReadyForPickup, 'order_ready_for_pickup', ['Asha', 'ORD-1', 'Kariakoo Collection Point']],
            [NotificationEventType::ShipmentCreated, 'order_shipped', ['Asha', 'ORD-1', 'Dar es Salaam']],
            [NotificationEventType::OrderDelivered, 'order_delivered', ['Asha', 'ORD-1']],
            [NotificationEventType::OrderCancelled, 'order_cancelled', ['Asha', 'ORD-1']],
        ];

        foreach ($cases as [$event, $name, $expected]) {
            $mapped = $mapper->map($this->notification($event, [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-1',
                'order_total' => '1000',
                'currency' => 'TZS',
                'pickup_location' => 'Kariakoo Collection Point',
                'destination' => 'Dar es Salaam',
                'location' => 'Must Not Appear',
            ]));

            $this->assertNotNull($mapped, $event->value);
            $this->assertSame($name, $mapped['name']);
            $this->assertSame('en_US', $mapped['language']);
            $this->assertSame($expected, $mapped['body_parameters']);
            $this->assertCount(count($expected), $mapped['body_parameters']);
        }
    }

    public function test_missing_required_pickup_or_destination_is_not_faked(): void
    {
        $mapper = app(GhalaWhatsAppTemplateMapper::class);

        $this->assertNull($mapper->map($this->notification(NotificationEventType::WarehouseReadyForPickup, [
            'customer_name' => 'Asha',
            'order_number' => 'ORD-1',
        ])));

        $this->assertNull($mapper->map($this->notification(NotificationEventType::ShipmentCreated, [
            'customer_name' => 'Asha',
            'order_number' => 'ORD-1',
        ])));
    }

    public function test_unmapped_event_returns_null(): void
    {
        $mapper = app(GhalaWhatsAppTemplateMapper::class);

        $this->assertNull($mapper->map($this->notification(NotificationEventType::TrackingUpdated, [
            'customer_name' => 'Asha',
            'order_number' => 'ORD-1',
        ])));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function notification(NotificationEventType $event, array $data): Notification
    {
        return Notification::factory()->make([
            'channel' => NotificationChannel::WhatsApp,
            'event_type' => $event->value,
            'type' => $event->value,
            'data' => $data,
        ]);
    }
}

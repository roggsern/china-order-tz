<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\ShipmentStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminShipmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_can_update_shipment_status(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $order = Order::factory()->pending()->create();

        $response = $this->patchJson("/api/v1/admin/orders/{$order->id}/shipment-status", [
            'shipment_status' => 'PAYMENT_CONFIRMED',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Shipment status updated successfully.')
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonPath('data.shipment_status', ShipmentStatus::PaymentConfirmed->value);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'shipment_status' => ShipmentStatus::PaymentConfirmed->value,
        ]);
    }

    public function test_inactive_admin_rejected(): void
    {
        Sanctum::actingAs(Admin::factory()->inactive()->create());

        $order = Order::factory()->create();

        $this->patchJson("/api/v1/admin/orders/{$order->id}/shipment-status", [
            'shipment_status' => 'PAYMENT_CONFIRMED',
        ])->assertForbidden();
    }

    public function test_customer_token_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $order = Order::factory()->create();

        $this->patchJson("/api/v1/admin/orders/{$order->id}/shipment-status", [
            'shipment_status' => 'PAYMENT_CONFIRMED',
        ])->assertUnauthorized();
    }

    public function test_guest_rejected(): void
    {
        $order = Order::factory()->create();

        $this->patchJson("/api/v1/admin/orders/{$order->id}/shipment-status", [
            'shipment_status' => 'PAYMENT_CONFIRMED',
        ])->assertUnauthorized();
    }

    public function test_valid_transition_succeeds(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $order = Order::factory()->create([
            'status' => OrderStatus::Pending,
            'shipment_status' => ShipmentStatus::OrderReceived,
        ]);

        $this->patchJson("/api/v1/admin/orders/{$order->id}/shipment-status", [
            'shipment_status' => 'SUPPLIER_PROCESSING',
        ])->assertUnprocessable();

        $response = $this->patchJson("/api/v1/admin/orders/{$order->id}/shipment-status", [
            'shipment_status' => 'PAYMENT_CONFIRMED',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.shipment_status', ShipmentStatus::PaymentConfirmed->value);
    }

    public function test_invalid_skip_transition_returns_validation_error(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $order = Order::factory()->pending()->create();

        $response = $this->patchJson("/api/v1/admin/orders/{$order->id}/shipment-status", [
            'shipment_status' => 'DELIVERED',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['shipment_status']);
    }

    public function test_invalid_backward_transition_returns_validation_error(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $order = Order::factory()->create([
            'shipment_status' => ShipmentStatus::ShippedFromChina,
        ]);

        $response = $this->patchJson("/api/v1/admin/orders/{$order->id}/shipment-status", [
            'shipment_status' => 'SUPPLIER_PROCESSING',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['shipment_status']);
    }

    public function test_shipment_timeline_reflects_updated_status(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $user = User::factory()->create();
        $order = Order::factory()->pending()->create(['user_id' => $user->id]);

        $this->patchJson("/api/v1/admin/orders/{$order->id}/shipment-status", [
            'shipment_status' => 'PAYMENT_CONFIRMED',
        ])->assertOk();

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/orders/{$order->id}/tracking")
            ->assertOk()
            ->assertJsonPath('data.current_status', ShipmentStatus::PaymentConfirmed->value);
    }

    public function test_shipment_history_audit_foundation_is_recorded(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $order = Order::factory()->pending()->create();

        $this->patchJson("/api/v1/admin/orders/{$order->id}/shipment-status", [
            'shipment_status' => 'PAYMENT_CONFIRMED',
        ])->assertOk();

        $this->assertDatabaseHas('shipment_status_histories', [
            'order_id' => $order->id,
            'admin_id' => $admin->id,
            'previous_status' => ShipmentStatus::OrderReceived->value,
            'new_status' => ShipmentStatus::PaymentConfirmed->value,
        ]);

        $this->assertSame(1, ShipmentStatusHistory::query()->where('order_id', $order->id)->count());
    }
}

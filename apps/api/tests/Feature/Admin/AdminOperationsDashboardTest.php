<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOperationsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_can_access_operations_dashboard(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $response = $this->getJson('/api/v1/admin/operations-dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'total_orders',
                        'pending_payments',
                        'total_customers',
                        'total_products',
                    ],
                    'shipments',
                    'alerts',
                ],
            ]);
    }

    public function test_inactive_admin_rejected(): void
    {
        Sanctum::actingAs(Admin::factory()->inactive()->create());

        $this->getJson('/api/v1/admin/operations-dashboard')->assertForbidden();
    }

    public function test_customer_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/operations-dashboard')->assertUnauthorized();
    }

    public function test_guest_rejected(): void
    {
        $this->getJson('/api/v1/admin/operations-dashboard')->assertUnauthorized();
    }

    public function test_summary_values_are_returned(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $user = User::factory()->create();
        Product::factory()->count(2)->create();
        $orders = Order::factory()->count(3)->create(['user_id' => $user->id]);

        Payment::factory()->create([
            'user_id' => $user->id,
            'order_id' => $orders[0]->id,
            'status' => PaymentStatus::Pending,
        ]);

        Payment::factory()->create([
            'user_id' => $user->id,
            'order_id' => $orders[1]->id,
            'status' => PaymentStatus::Pending,
        ]);

        $response = $this->getJson('/api/v1/admin/operations-dashboard');

        $response->assertOk()
            ->assertJsonPath('data.summary.total_orders', 3)
            ->assertJsonPath('data.summary.pending_payments', 2)
            ->assertJsonPath('data.summary.total_customers', 1)
            ->assertJsonPath('data.summary.total_products', 2);
    }

    public function test_shipment_statistics_are_returned(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        Order::factory()->create([
            'status' => OrderStatus::Pending,
        ]);

        Order::factory()->create([
            'shipment_status' => ShipmentStatus::PackedForExport,
        ]);

        $response = $this->getJson('/api/v1/admin/operations-dashboard');

        $response->assertOk()
            ->assertJsonPath('data.shipments.order_received', 1)
            ->assertJsonPath('data.shipments.packed_for_export', 1);
    }

    public function test_shipment_statistics_use_shipment_status_enum(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $response = $this->getJson('/api/v1/admin/operations-dashboard');

        $response->assertOk();

        $shipments = $response->json('data.shipments');

        $this->assertCount(count(ShipmentStatus::timeline()), $shipments);

        foreach (ShipmentStatus::timeline() as $status) {
            $this->assertArrayHasKey($status->value, $shipments);
            $this->assertIsInt($shipments[$status->value]);
        }
    }

    public function test_alerts_are_returned(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $user = User::factory()->create();

        Order::factory()->create([
            'user_id' => $user->id,
            'shipment_status' => ShipmentStatus::SupplierProcessing,
            'shipment_status_updated_at' => now()->subDays(4),
        ]);

        Payment::factory()->create([
            'user_id' => $user->id,
            'status' => PaymentStatus::Pending,
        ]);

        Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/admin/operations-dashboard');

        $response->assertOk()
            ->assertJsonFragment(['type' => 'supplier_processing_delayed'])
            ->assertJsonFragment(['type' => 'pending_payments'])
            ->assertJsonFragment(['type' => 'cancelled_orders_today']);
    }
}

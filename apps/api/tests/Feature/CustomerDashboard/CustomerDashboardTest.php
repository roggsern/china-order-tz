<?php

namespace Tests\Feature\CustomerDashboard;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_customer_can_access_dashboard(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Customer',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.customer.id', $user->id)
            ->assertJsonPath('data.customer.name', 'Jane Customer')
            ->assertJsonStructure([
                'data' => [
                    'customer' => ['id', 'name'],
                    'summary' => [
                        'active_orders',
                        'in_transit_orders',
                        'pending_payments',
                        'completed_orders',
                    ],
                    'recent_orders',
                    'quick_actions',
                ],
            ])
            ->assertJsonCount(4, 'data.quick_actions');
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }

    public function test_admin_token_rejected_on_customer_dashboard(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }

    public function test_summary_values_are_correct(): void
    {
        $user = User::factory()->create();

        Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
        ]);

        Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Confirmed,
        ]);

        Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Shipped,
        ]);

        Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Delivered,
        ]);

        Payment::factory()->create([
            'user_id' => $user->id,
            'status' => PaymentStatus::Pending,
        ]);

        Payment::factory()->completed()->create([
            'user_id' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.summary.active_orders', 2)
            ->assertJsonPath('data.summary.in_transit_orders', 1)
            ->assertJsonPath('data.summary.pending_payments', 1)
            ->assertJsonPath('data.summary.completed_orders', 1);
    }

    public function test_only_latest_five_customer_orders_are_returned(): void
    {
        $user = User::factory()->create();

        for ($day = 6; $day >= 0; $day--) {
            Order::factory()->create([
                'user_id' => $user->id,
                'order_number' => 'COT-00000'.$day,
                'created_at' => now()->subDays($day),
            ]);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard');

        $response->assertOk()
            ->assertJsonCount(5, 'data.recent_orders');

        $returnedNumbers = collect($response->json('data.recent_orders'))
            ->pluck('order_number')
            ->all();

        $this->assertEquals([
            'COT-000000',
            'COT-000001',
            'COT-000002',
            'COT-000003',
            'COT-000004',
        ], $returnedNumbers);
    }

    public function test_another_customers_orders_never_appear(): void
    {
        $user = User::factory()->create(['name' => 'Primary User']);
        $otherUser = User::factory()->create(['name' => 'Other User']);

        Order::factory()->count(2)->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
        ]);

        Order::factory()->count(3)->create([
            'user_id' => $otherUser->id,
            'status' => OrderStatus::Delivered,
        ]);

        Payment::factory()->count(2)->create([
            'user_id' => $otherUser->id,
            'status' => PaymentStatus::Pending,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.summary.active_orders', 2)
            ->assertJsonPath('data.summary.in_transit_orders', 0)
            ->assertJsonPath('data.summary.pending_payments', 0)
            ->assertJsonPath('data.summary.completed_orders', 0)
            ->assertJsonCount(2, 'data.recent_orders');

        $returnedOrderIds = collect($response->json('data.recent_orders'))
            ->pluck('id')
            ->all();

        $otherUserOrderIds = Order::query()
            ->where('user_id', $otherUser->id)
            ->pluck('id')
            ->all();

        $this->assertEmpty(array_intersect($returnedOrderIds, $otherUserOrderIds));
    }
}

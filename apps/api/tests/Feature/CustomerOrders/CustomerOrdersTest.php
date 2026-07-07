<?php

namespace Tests\Feature\CustomerOrders;

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_customer_can_list_orders(): void
    {
        $user = User::factory()->create();

        Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => 'COT-000001',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/orders');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'order_number',
                        'source',
                        'status',
                        'total',
                        'created_at',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/orders')->assertUnauthorized();
    }

    public function test_admin_token_rejected_on_customer_orders(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $this->getJson('/api/v1/orders')->assertUnauthorized();
    }

    public function test_customer_cannot_see_another_customers_orders(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownOrder = Order::factory()->create(['user_id' => $user->id]);
        Order::factory()->count(2)->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/orders');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownOrder->id);
    }

    public function test_newest_orders_appear_first(): void
    {
        $user = User::factory()->create();

        Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => 'COT-000001',
            'created_at' => now()->subDays(2),
        ]);

        Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => 'COT-000002',
            'created_at' => now()->subDay(),
        ]);

        Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => 'COT-000003',
            'created_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/orders');

        $response->assertOk()
            ->assertJsonPath('data.0.order_number', 'COT-000003')
            ->assertJsonPath('data.1.order_number', 'COT-000002')
            ->assertJsonPath('data.2.order_number', 'COT-000001');
    }

    public function test_pagination_works(): void
    {
        $user = User::factory()->create();

        Order::factory()->count(12)->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $firstPage = $this->getJson('/api/v1/orders?page=1&per_page=10');
        $secondPage = $this->getJson('/api/v1/orders?page=2&per_page=10');

        $firstPage->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 12);

        $secondPage->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_active_filter_works(): void
    {
        $user = User::factory()->create();

        $pendingOrder = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Pending,
        ]);

        $paidOrder = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
        ]);

        Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Delivered,
        ]);

        Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Cancelled,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/orders?filter=active');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertEqualsCanonicalizing(
            [$pendingOrder->id, $paidOrder->id],
            $returnedIds,
        );
    }

    public function test_completed_filter_works(): void
    {
        $user = User::factory()->create();

        $deliveredOrder = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Delivered,
        ]);

        Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Processing,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/orders?filter=completed');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $deliveredOrder->id);
    }
}

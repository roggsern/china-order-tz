<?php

namespace Tests\Feature\Admin;

use App\Enums\CommerceChannelCode;
use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\CommerceChannel;
use App\Models\Order;
use App\Models\User;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\CommerceChannelSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOrderIndexFiltersTest extends TestCase
{
    use RefreshDatabase;

    private CommerceChannel $china;

    private CommerceChannel $local;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(AdminPermissionSeeder::class);
        $this->seed(CommerceChannelSeeder::class);

        $this->china = CommerceChannel::query()
            ->where('code', CommerceChannelCode::ChinaImport->value)
            ->firstOrFail();
        $this->local = CommerceChannel::query()
            ->where('code', CommerceChannelCode::TzLocal->value)
            ->firstOrFail();
    }

    public function test_index_without_filters_paginates_and_exposes_channel(): void
    {
        $order = $this->makeOrder([
            'order_number' => 'COT-CHANNEL-1',
            'status' => OrderStatus::Paid,
            'commerce_channel_id' => $this->china->id,
            'commerce_channel_snapshot' => [
                'id' => $this->china->id,
                'code' => CommerceChannelCode::ChinaImport->value,
                'name' => $this->china->name,
            ],
        ]);

        Sanctum::actingAs($this->viewer());
        $response = $this->getJson('/api/v1/admin/orders');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.per_page', 15);

        $row = collect($response->json('data'))->firstWhere('id', $order->id);
        $this->assertNotNull($row);
        $this->assertSame(CommerceChannelCode::ChinaImport->value, $row['commerce_channel_code']);
        $this->assertSame($this->china->id, $row['commerce_channel_id']);
        $this->assertSame(CommerceChannelCode::ChinaImport->value, $row['commerce_channel']['code'] ?? null);
    }

    public function test_status_filter_still_works(): void
    {
        $paid = $this->makeOrder([
            'order_number' => 'COT-STATUS-PAID',
            'status' => OrderStatus::Paid,
            'commerce_channel_id' => $this->china->id,
        ]);
        $this->makeOrder([
            'order_number' => 'COT-STATUS-PENDING',
            'status' => OrderStatus::PendingPayment,
            'commerce_channel_id' => $this->local->id,
        ]);

        Sanctum::actingAs($this->viewer());
        $response = $this->getJson('/api/v1/admin/orders?status=paid');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($paid->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_search_by_order_number(): void
    {
        $match = $this->makeOrder([
            'order_number' => 'COT-SEARCH-UNIQUE-99',
            'status' => OrderStatus::Paid,
            'commerce_channel_id' => $this->china->id,
        ]);
        $this->makeOrder([
            'order_number' => 'COT-OTHER-11',
            'status' => OrderStatus::Paid,
            'commerce_channel_id' => $this->local->id,
        ]);

        Sanctum::actingAs($this->viewer());
        $response = $this->getJson('/api/v1/admin/orders?q=SEARCH-UNIQUE');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$match->id], $ids);
    }

    public function test_search_by_customer_email_and_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Asha Mwinyi',
            'email' => 'asha.mwinyi@example.test',
            'first_name' => 'Asha',
            'last_name' => 'Mwinyi',
        ]);
        $match = $this->makeOrder([
            'user_id' => $user->id,
            'order_number' => 'COT-CUST-1',
            'status' => OrderStatus::Paid,
            'commerce_channel_id' => $this->china->id,
        ]);
        $this->makeOrder([
            'order_number' => 'COT-CUST-2',
            'status' => OrderStatus::Paid,
            'commerce_channel_id' => $this->local->id,
        ]);

        Sanctum::actingAs($this->viewer());

        $byEmail = $this->getJson('/api/v1/admin/orders?q=asha.mwinyi@example.test');
        $byEmail->assertOk();
        $this->assertSame([$match->id], collect($byEmail->json('data'))->pluck('id')->all());

        $byName = $this->getJson('/api/v1/admin/orders?q=Mwinyi');
        $byName->assertOk();
        $this->assertSame([$match->id], collect($byName->json('data'))->pluck('id')->all());
    }

    public function test_commerce_channel_filters_do_not_leak_across_channels(): void
    {
        $chinaOrder = $this->makeOrder([
            'order_number' => 'COT-CHINA-ONLY',
            'status' => OrderStatus::Paid,
            'commerce_channel_id' => $this->china->id,
            'commerce_channel_snapshot' => [
                'id' => $this->china->id,
                'code' => CommerceChannelCode::ChinaImport->value,
            ],
        ]);
        $localOrder = $this->makeOrder([
            'order_number' => 'COT-LOCAL-ONLY',
            'status' => OrderStatus::Paid,
            'commerce_channel_id' => $this->local->id,
            'commerce_channel_snapshot' => [
                'id' => $this->local->id,
                'code' => CommerceChannelCode::TzLocal->value,
            ],
        ]);

        Sanctum::actingAs($this->viewer());

        $china = $this->getJson('/api/v1/admin/orders?commerce_channel=CHINA_IMPORT');
        $china->assertOk();
        $chinaIds = collect($china->json('data'))->pluck('id')->all();
        $this->assertContains($chinaOrder->id, $chinaIds);
        $this->assertNotContains($localOrder->id, $chinaIds);

        $local = $this->getJson('/api/v1/admin/orders?commerce_channel=TZ_LOCAL');
        $local->assertOk();
        $localIds = collect($local->json('data'))->pluck('id')->all();
        $this->assertContains($localOrder->id, $localIds);
        $this->assertNotContains($chinaOrder->id, $localIds);
    }

    public function test_invalid_commerce_channel_is_rejected(): void
    {
        Sanctum::actingAs($this->viewer());

        $this->getJson('/api/v1/admin/orders?commerce_channel=china')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commerce_channel']);
    }

    public function test_show_exposes_canonical_channel(): void
    {
        $order = $this->makeOrder([
            'order_number' => 'COT-SHOW-CHANNEL',
            'status' => OrderStatus::Paid,
            'commerce_channel_id' => $this->local->id,
            'commerce_channel_snapshot' => [
                'id' => $this->local->id,
                'code' => CommerceChannelCode::TzLocal->value,
            ],
        ]);

        Sanctum::actingAs($this->viewer());
        $this->getJson('/api/v1/admin/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.commerce_channel_code', CommerceChannelCode::TzLocal->value)
            ->assertJsonPath('data.commerce_channel.code', CommerceChannelCode::TzLocal->value);
    }

    private function viewer(): Admin
    {
        return Admin::factory()->withPermissions([AdminPermissions::ORDERS_VIEW])->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeOrder(array $attributes): Order
    {
        return Order::factory()->create($attributes);
    }
}

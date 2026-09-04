<?php

namespace Tests\Feature\Admin;

use App\Enums\CommerceChannelCode;
use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\CommerceChannel;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\CommerceChannelSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOrderPaginationAndShowTest extends TestCase
{
    use RefreshDatabase;

    private CommerceChannel $china;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(AdminPermissionSeeder::class);
        $this->seed(CommerceChannelSeeder::class);

        $this->china = CommerceChannel::query()
            ->where('code', CommerceChannelCode::ChinaImport->value)
            ->firstOrFail();
    }

    public function test_older_paid_order_is_absent_from_page_one_but_show_and_later_page_resolve_it(): void
    {
        $orders = [];
        for ($i = 1; $i <= 25; $i++) {
            $orders[] = $this->makePaidOrder(
                sprintf('COT-RANK-%02d', $i),
                now()->subMinutes(26 - $i),
            );
        }

        $oldest = $orders[0];
        $this->assertSame('COT-RANK-01', $oldest->order_number);

        $product = Product::factory()->create(['fulfillment_source' => 'buy_from_tz']);
        OrderItem::factory()->create([
            'order_id' => $oldest->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'unit_price' => $oldest->total,
            'total_price' => $oldest->total,
            'line_total' => $oldest->total,
        ]);
        $fulfillment = app(FulfillmentEngine::class)->createForOrder($oldest->fresh(['items.product']));

        Sanctum::actingAs($this->viewer());

        $pageOne = $this->getJson('/api/v1/admin/orders?page=1&per_page=20');
        $pageOne->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.last_page', 2);
        $pageOneIds = collect($pageOne->json('data'))->pluck('id')->all();
        $this->assertCount(20, $pageOneIds);
        $this->assertNotContains($oldest->id, $pageOneIds);

        $this->getJson('/api/v1/admin/orders/'.$oldest->id)
            ->assertOk()
            ->assertJsonPath('data.id', $oldest->id)
            ->assertJsonPath('data.order_number', 'COT-RANK-01')
            ->assertJsonPath('data.status', OrderStatus::Paid->value);

        $pageTwo = $this->getJson('/api/v1/admin/orders?page=2&per_page=20');
        $pageTwo->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2);
        $pageTwoIds = collect($pageTwo->json('data'))->pluck('id')->all();
        $this->assertContains($oldest->id, $pageTwoIds);

        $this->getJson('/api/v1/admin/fulfillments/'.$fulfillment->id)
            ->assertOk()
            ->assertJsonPath('data.id', $fulfillment->id)
            ->assertJsonPath('data.order_id', $oldest->id)
            ->assertJsonPath('data.order.id', $oldest->id);
    }

    private function viewer(): Admin
    {
        return Admin::factory()->withPermissions([AdminPermissions::ORDERS_VIEW])->create();
    }

    private function makePaidOrder(string $orderNumber, \DateTimeInterface $createdAt): Order
    {
        return Order::factory()->create([
            'order_number' => $orderNumber,
            'status' => OrderStatus::Paid,
            'paid_at' => $createdAt,
            'placed_at' => $createdAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'commerce_channel_id' => $this->china->id,
            'commerce_channel_snapshot' => [
                'id' => $this->china->id,
                'code' => CommerceChannelCode::ChinaImport->value,
            ],
        ]);
    }
}

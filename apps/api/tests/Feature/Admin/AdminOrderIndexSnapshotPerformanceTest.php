<?php

namespace Tests\Feature\Admin;

use App\Actions\AdminOrders\GetAdminOrdersAction;
use App\Enums\CommerceChannelCode;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Resources\AdminOrderIndexResource;
use App\Models\Admin;
use App\Models\CommerceChannel;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\CommerceChannelSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOrderIndexSnapshotPerformanceTest extends TestCase
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

    public function test_index_uses_item_snapshots_after_product_rename_and_image_change(): void
    {
        $product = Product::factory()->create([
            'name' => 'Live Catalog Name',
        ]);
        ProductVariant::factory()->count(3)->create([
            'product_id' => $product->id,
        ]);

        $order = $this->makePaidOrder('COT-SNAP-INDEX-1', now()->subMinutes(3));
        $this->addSnapshotItem($order, $product, [
            'product_name_snapshot' => 'Purchased Snapshot Name',
            'product_image_snapshot' => 'https://cdn.example.test/order-snapshot.jpg',
            'variant_name_snapshot' => 'Black • 128GB',
            'sku_snapshot' => 'SNAP-SKU',
            'quantity' => 2,
        ]);

        $product->update(['name' => 'Changed After Purchase']);

        Sanctum::actingAs($this->viewer());
        $response = $this->getJson('/api/v1/admin/orders?page=1&per_page=20');
        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $order->id);
        $this->assertIsArray($row);
        $item = $row['items'][0];
        $this->assertSame('Purchased Snapshot Name', $item['product_name_snapshot']);
        $this->assertSame('Purchased Snapshot Name', $item['product_name']);
        $this->assertSame('https://cdn.example.test/order-snapshot.jpg', $item['product_image_snapshot']);
        $this->assertSame('https://cdn.example.test/order-snapshot.jpg', $item['image_snapshot']);
        $this->assertSame('Black • 128GB', $item['variant_name_snapshot']);
        $this->assertArrayNotHasKey('product', $item);
        $this->assertArrayNotHasKey('variant', $item);
        $this->assertArrayNotHasKey('variants_count', $item);
        $this->assertArrayNotHasKey('price_range', $item);
        $this->assertArrayNotHasKey('stock_summary', $item);

        $encoded = json_encode($row);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('Changed After Purchase', $encoded);
        $this->assertStringNotContainsString('variants_count', $encoded);
        $this->assertStringNotContainsString('price_range', $encoded);
        $this->assertStringNotContainsString('stock_summary', $encoded);
        $this->assertArrayNotHasKey('status_history', $row);
        $this->assertArrayNotHasKey('refund_transactions', $row);
        $this->assertArrayNotHasKey('fulfillment', $row);
        $this->assertSame(OrderStatus::Paid->value, $row['status']);
    }

    public function test_index_keeps_pagination_meta_and_does_not_n_plus_one_live_catalog(): void
    {
        $product = Product::factory()->create(['name' => 'Live Catalog Name']);
        ProductVariant::factory()->count(4)->create(['product_id' => $product->id]);

        $statuses = [
            OrderStatus::Paid,
            OrderStatus::PendingPayment,
            OrderStatus::Processing,
            OrderStatus::Shipped,
            OrderStatus::Delivered,
        ];

        for ($i = 1; $i <= 60; $i++) {
            $status = $statuses[($i - 1) % count($statuses)];
            $order = $this->makeOrder(
                sprintf('COT-IDX-%02d', $i),
                now()->subMinutes(50 - $i),
                $status,
            );
            for ($item = 0; $item < 3; $item++) {
                $this->addSnapshotItem($order, $product, [
                    'product_name_snapshot' => 'Purchased Snapshot Name '.$item,
                    'product_image_snapshot' => 'https://cdn.example.test/order-snapshot-'.$item.'.jpg',
                    'quantity' => 1,
                ]);
            }
        }

        $product->update(['name' => 'Changed After Purchase']);

        Sanctum::actingAs($this->viewer());
        DB::flushQueryLog();
        DB::enableQueryLog();
        $started = microtime(true);

        $response = $this->getJson('/api/v1/admin/orders?page=3&per_page=20');

        $elapsedMs = (int) round((microtime(true) - $started) * 1000);
        $queryLog = DB::getQueryLog();
        $queryCount = count($queryLog);
        DB::disableQueryLog();

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.current_page', 3)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 60)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.from', 41)
            ->assertJsonPath('meta.to', 60);

        $rows = $response->json('data');
        $this->assertCount(20, $rows);
        $payloadBytes = strlen((string) $response->getContent());

        $sql = collect($queryLog)->pluck('query')->implode(' ');
        $this->assertStringNotContainsString('china_commercial_stocks', $sql);
        $this->assertStringNotContainsString('product_media', $sql);
        $this->assertStringNotContainsString('Changed After Purchase', (string) json_encode($rows));

        foreach ($rows as $row) {
            $this->assertArrayNotHasKey('product', $row['items'][0] ?? []);
            $this->assertSame('Purchased Snapshot Name 0', $row['items'][0]['product_name'] ?? null);
            $this->assertContains($row['status'], [
                OrderStatus::Paid->value,
                OrderStatus::PendingPayment->value,
                OrderStatus::Processing->value,
                OrderStatus::Shipped->value,
                OrderStatus::Delivered->value,
            ]);
        }

        $this->assertLessThan(
            50,
            $queryCount,
            "Admin orders index used {$queryCount} queries in {$elapsedMs}ms with {$payloadBytes} byte payload; expected far below the forensic ~7,129.",
        );
        $this->assertLessThan(
            200_000,
            $payloadBytes,
            "Admin orders index payload was {$payloadBytes} bytes; expected a large drop from ~527KB.",
        );

        $this->app->instance('request', Request::create('/api/v1/admin/orders', 'GET', [
            'page' => 3,
            'per_page' => 20,
        ]));

        DB::flushQueryLog();
        DB::enableQueryLog();
        $loadStarted = microtime(true);
        $paginator = app(GetAdminOrdersAction::class)->handle(['per_page' => 20]);
        $loadMs = (int) round((microtime(true) - $loadStarted) * 1000);
        $loadQueries = count(DB::getQueryLog());

        DB::flushQueryLog();
        $serializeStarted = microtime(true);
        AdminOrderIndexResource::collection($paginator)->resolve();
        $serializeMs = (int) round((microtime(true) - $serializeStarted) * 1000);
        $serializeQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(20, $paginator->items());
        $this->assertLessThan(25, $loadQueries + $serializeQueries);
        $this->assertSame(0, $serializeQueries, 'Index serialization must not query live catalog.');

        fwrite(STDERR, sprintf(
            "\n[admin-orders-index] http_queries=%d http_ms=%d payload_bytes=%d load_queries=%d load_ms=%d serialize_queries=%d serialize_ms=%d\n",
            $queryCount,
            $elapsedMs,
            $payloadBytes,
            $loadQueries,
            $loadMs,
            $serializeQueries,
            $serializeMs,
        ));
    }

    public function test_show_is_unchanged_and_still_omits_product_resource(): void
    {
        $order = $this->makePaidOrder('COT-SHOW-REGRESSION', now()->subMinutes(8));
        $product = Product::factory()->create(['name' => 'Live Catalog Name']);
        $this->addSnapshotItem($order, $product, [
            'product_name_snapshot' => 'Purchased Snapshot Name',
            'product_image_snapshot' => 'https://cdn.example.test/order-snapshot.jpg',
        ]);
        $product->update(['name' => 'Changed After Purchase']);

        Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'status' => PaymentStatus::Paid,
            'method' => PaymentMethod::Cash,
        ]);

        Sanctum::actingAs($this->viewer());
        $payload = $this->getJson('/api/v1/admin/orders/'.$order->id)
            ->assertOk()
            ->json('data');

        $this->assertSame('Purchased Snapshot Name', $payload['items'][0]['product_name']);
        $this->assertArrayNotHasKey('product', $payload['items'][0]);
        $this->assertArrayHasKey('shipping_address', $payload);
        $this->assertArrayHasKey('payment', $payload);
        $this->assertStringNotContainsString('Changed After Purchase', (string) json_encode($payload));
        $this->assertStringNotContainsString('variants_count', (string) json_encode($payload));
    }

    private function viewer(): Admin
    {
        return Admin::factory()->withPermissions([AdminPermissions::ORDERS_VIEW])->create();
    }

    private function makePaidOrder(string $orderNumber, \DateTimeInterface $createdAt): Order
    {
        return $this->makeOrder($orderNumber, $createdAt, OrderStatus::Paid);
    }

    private function makeOrder(string $orderNumber, \DateTimeInterface $createdAt, OrderStatus $status): Order
    {
        return Order::factory()->create([
            'order_number' => $orderNumber,
            'status' => $status,
            'paid_at' => $status === OrderStatus::PendingPayment ? null : $createdAt,
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function addSnapshotItem(Order $order, Product $product, array $attributes): OrderItem
    {
        $quantity = (int) ($attributes['quantity'] ?? 1);
        $unitPrice = $attributes['unit_price'] ?? 15000;

        return OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $unitPrice * $quantity,
            'total_price' => $unitPrice * $quantity,
            'currency' => 'TZS',
            'shipping_method' => 'sea',
            'shipping_price' => 4000,
            'shipping_subtotal' => 4000 * $quantity,
            'product_name_snapshot' => $attributes['product_name_snapshot'] ?? 'Purchased Snapshot Name',
            'product_image_snapshot' => $attributes['product_image_snapshot'] ?? 'https://cdn.example.test/order-snapshot.jpg',
            'variant_name_snapshot' => $attributes['variant_name_snapshot'] ?? null,
            'sku_snapshot' => $attributes['sku_snapshot'] ?? 'SNAP-SKU',
        ]);
    }
}

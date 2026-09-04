<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ShippingAddress;
use App\Models\User;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOrderShowOperationalDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(AdminPermissionSeeder::class);
    }

    public function test_show_exposes_snippe_method_provider_paid_at_and_safe_reference(): void
    {
        $order = $this->makePaidOrder();
        $completedAt = Carbon::parse('2026-09-01 19:23:42');

        PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Snippe,
            'merchant_reference' => 'COTZ-PAY-SNIPPE-SHOW',
            'provider_reference' => 'SNIPPE-1306',
            'status' => PaymentTransactionStatus::Successful,
            'amount' => 124000,
            'completed_at' => $completedAt,
            'request_payload' => ['secret' => 'should-not-leak'],
            'response_payload' => ['token' => 'should-not-leak'],
            'verification_payload' => ['signature' => 'should-not-leak'],
        ]);

        $payload = $this->show($order);

        $this->assertSame(PaymentMethod::Snippe->value, $payload['payment']['payment_method']);
        $this->assertSame(PaymentProvider::Snippe->value, $payload['payment']['provider']);
        $this->assertSame('COTZ-PAY-SNIPPE-SHOW', $payload['payment']['reference']);
        $this->assertNotEmpty($payload['payment']['paid_at']);
        $this->assertSame(
            ['payment_status', 'payment_method', 'provider', 'reference', 'paid_at'],
            array_keys($payload['payment']),
        );
        $this->assertArrayNotHasKey('payment_transactions', $payload);
        $this->assertArrayNotHasKey('request_payload', $payload);
        $encoded = json_encode($payload);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('should-not-leak', $encoded);
        $this->assertStringNotContainsString('webhook', $encoded);
    }

    public function test_show_exposes_nmb_method_and_provider(): void
    {
        $order = $this->makePaidOrder();

        PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
            'merchant_reference' => 'COTZ-PAY-NMB-SHOW',
            'status' => PaymentTransactionStatus::Successful,
            'completed_at' => now()->subMinutes(6),
        ]);

        $payload = $this->show($order);

        $this->assertSame(PaymentMethod::Nmb->value, $payload['payment']['payment_method']);
        $this->assertSame(PaymentProvider::Nmb->value, $payload['payment']['provider']);
        $this->assertSame('COTZ-PAY-NMB-SHOW', $payload['payment']['reference']);
    }

    public function test_show_pay_at_office_does_not_require_payment_transaction(): void
    {
        $user = User::factory()->create();
        $paidAt = now()->subMinutes(15);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => $paidAt,
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Paid,
            'reference' => 'OFFICE-SHOW-001',
            'paid_at' => $paidAt,
            'metadata' => ['api_key' => 'do-not-copy-into-snapshot'],
        ]);

        $this->assertSame(0, PaymentTransaction::query()->where('order_id', $order->id)->count());

        $payload = $this->show($order);

        $this->assertSame(PaymentMethod::Cash->value, $payload['payment']['payment_method']);
        $this->assertSame('office', $payload['payment']['provider']);
        $this->assertSame('OFFICE-SHOW-001', $payload['payment']['reference']);
        $this->assertArrayNotHasKey('metadata', $payload['payment']);
        $this->assertStringNotContainsString('do-not-copy-into-snapshot', json_encode($payload['payment']));
    }

    public function test_show_selects_successful_transaction_not_newer_failed_attempt(): void
    {
        $order = $this->makePaidOrder();

        PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Snippe,
            'merchant_reference' => 'COTZ-PAY-CANONICAL',
            'status' => PaymentTransactionStatus::Successful,
            'created_at' => now()->subMinutes(30),
            'completed_at' => now()->subMinutes(28),
        ]);
        PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Snippe,
            'merchant_reference' => 'COTZ-PAY-FAILED-NEW',
            'status' => PaymentTransactionStatus::Failed,
            'created_at' => now()->subMinutes(2),
        ]);

        $payload = $this->show($order);

        $this->assertSame('COTZ-PAY-CANONICAL', $payload['payment']['reference']);
    }

    public function test_show_includes_shipping_snapshot_and_keeps_null_when_absent(): void
    {
        $order = $this->makePaidOrder();
        ShippingAddress::factory()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'first_name' => 'Asha',
            'last_name' => 'Nyerere',
            'phone' => '0712000000',
            'address_line_1' => 'Plot 12',
            'address_line_2' => 'Kariakoo',
            'city' => 'Dar es Salaam',
            'region' => 'Dar es Salaam',
            'postal_code' => null,
            'country' => 'Tanzania',
        ]);

        $withAddress = $this->show($order);
        $this->assertIsArray($withAddress['shipping_address']);
        $this->assertSame('Asha', $withAddress['shipping_address']['first_name']);
        $this->assertSame('Plot 12', $withAddress['shipping_address']['address_line_1']);
        $this->assertSame('Kariakoo', $withAddress['shipping_address']['address_line_2']);
        $this->assertNull($withAddress['shipping_address']['postal_code']);
        $this->assertSame('Tanzania', $withAddress['shipping_address']['country']);

        $bare = $this->makePaidOrder();
        $withoutAddress = $this->show($bare);
        $this->assertNull($withoutAddress['shipping_address']);
    }

    public function test_show_items_use_immutable_snapshots_without_product_resource(): void
    {
        $order = $this->makePaidOrder();
        $product = Product::factory()->create(['name' => 'Live Catalog Name']);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Live Catalog Name',
            'product_name_snapshot' => 'Purchased Snapshot Name',
            'product_image_snapshot' => 'https://cdn.example.test/order-snapshot.jpg',
            'sku_snapshot' => 'SNAP-SKU',
            'quantity' => 2,
            'unit_price' => 15000,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name_snapshot' => 'Second Snapshot Item',
            'quantity' => 1,
            'unit_price' => 8000,
        ]);

        $product->update(['name' => 'Changed After Purchase']);

        Sanctum::actingAs($this->viewer());
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/v1/admin/orders/'.$order->id);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        $item = $response->json('data.items.0');
        $this->assertSame('Purchased Snapshot Name', $item['product_name_snapshot']);
        $this->assertSame('Purchased Snapshot Name', $item['product_name']);
        $this->assertSame('https://cdn.example.test/order-snapshot.jpg', $item['product_image_snapshot']);
        $this->assertArrayNotHasKey('product', $item);
        $this->assertArrayNotHasKey('variant', $item);
        $encoded = json_encode($response->json('data'));
        $this->assertStringNotContainsString('Changed After Purchase', (string) $encoded);
        $this->assertStringNotContainsString('variants_count', (string) $encoded);
        $this->assertStringNotContainsString('price_range', (string) $encoded);
        $this->assertStringNotContainsString('stock_summary', (string) $encoded);

        // Forensic baseline was ~67 queries (11 load + 56 ProductResource). Stay far below that.
        $this->assertLessThan(
            30,
            $queryCount,
            'Admin order show serialized with '.$queryCount.' queries; expected a large drop from ~67.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function show(Order $order): array
    {
        Sanctum::actingAs($this->viewer());

        return $this->getJson('/api/v1/admin/orders/'.$order->id)
            ->assertOk()
            ->json('data');
    }

    private function makePaidOrder(): Order
    {
        return Order::factory()->create([
            'status' => OrderStatus::Paid,
            'paid_at' => now()->subMinutes(10),
        ]);
    }

    private function viewer(): Admin
    {
        return Admin::factory()->withPermissions([AdminPermissions::ORDERS_VIEW])->create();
    }
}

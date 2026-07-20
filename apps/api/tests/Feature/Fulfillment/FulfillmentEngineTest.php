<?php

namespace Tests\Feature\Fulfillment;

use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStrategy;
use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Services\Fulfillment\Strategies\ChinaFulfillmentStrategy;
use App\Services\Fulfillment\Strategies\LocalFulfillmentStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FulfillmentEngineTest extends TestCase
{
    use RefreshDatabase;

    private function makePaidOrderWithProduct(array $productAttrs = []): Order
    {
        $user = User::factory()->create();
        $product = Product::factory()->create($productAttrs);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
            'total' => 25000,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'unit_price' => 25000,
            'total_price' => 25000,
            'line_total' => 25000,
        ]);

        return $order->fresh(['items.product.supplier']);
    }

    public function test_creates_fulfillment_for_paid_order(): void
    {
        $order = $this->makePaidOrderWithProduct([
            'fulfillment_source' => 'buy_from_tz',
        ]);

        /** @var FulfillmentEngine $engine */
        $engine = app(FulfillmentEngine::class);
        $fulfillment = $engine->createForOrder($order);

        $this->assertInstanceOf(Fulfillment::class, $fulfillment);
        $this->assertSame(FulfillmentStrategy::Local, $fulfillment->strategy);
        $this->assertSame(FulfillmentStatus::Pending, $fulfillment->status);
        $this->assertTrue($order->fresh()->fulfillment->is($fulfillment));
    }

    public function test_rejects_unpaid_order(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::PendingPayment,
            'paid_at' => null,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(FulfillmentEngine::class)->createForOrder($order);
    }

    public function test_resolves_local_strategy_when_all_items_local(): void
    {
        $order = $this->makePaidOrderWithProduct([
            'fulfillment_source' => 'buy_from_tz',
        ]);

        $strategy = app(FulfillmentEngine::class)->resolveStrategy($order);
        $this->assertInstanceOf(LocalFulfillmentStrategy::class, $strategy);
        $this->assertSame(FulfillmentStrategy::Local, $strategy->key());
    }

    public function test_resolves_china_strategy_when_any_item_requires_china(): void
    {
        $order = $this->makePaidOrderWithProduct([
            'fulfillment_source' => 'imported_from_china',
        ]);

        $strategy = app(FulfillmentEngine::class)->resolveStrategy($order);
        $this->assertInstanceOf(ChinaFulfillmentStrategy::class, $strategy);
        $this->assertSame(FulfillmentStrategy::China, $strategy->key());
    }

    public function test_creation_is_idempotent(): void
    {
        $order = $this->makePaidOrderWithProduct([
            'fulfillment_source' => 'buy_from_tz',
        ]);

        $engine = app(FulfillmentEngine::class);
        $first = $engine->createForOrder($order);
        $second = $engine->createForOrder($order->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Fulfillment::query()->where('order_id', $order->id)->count());
    }

    public function test_status_transitions(): void
    {
        $order = $this->makePaidOrderWithProduct(['fulfillment_source' => 'buy_from_tz']);
        $engine = app(FulfillmentEngine::class);
        $fulfillment = $engine->createForOrder($order);

        $processing = $engine->updateStatus($fulfillment, [
            'status' => FulfillmentStatus::Processing->value,
        ]);
        $this->assertSame(FulfillmentStatus::Processing, $processing->status);
        $this->assertNotNull($processing->started_at);

        $ready = $engine->updateStatus($processing, [
            'status' => FulfillmentStatus::ReadyForShipping->value,
        ]);
        $this->assertSame(FulfillmentStatus::ReadyForShipping, $ready->status);

        $shipped = $engine->updateStatus($ready, [
            'status' => FulfillmentStatus::Shipped->value,
        ]);
        $delivered = $engine->updateStatus($shipped, [
            'status' => FulfillmentStatus::Delivered->value,
        ]);
        $this->assertSame(FulfillmentStatus::Delivered, $delivered->status);
        $this->assertNotNull($delivered->completed_at);
    }

    public function test_invalid_status_transition_rejected(): void
    {
        $order = $this->makePaidOrderWithProduct(['fulfillment_source' => 'buy_from_tz']);
        $engine = app(FulfillmentEngine::class);
        $fulfillment = $engine->createForOrder($order);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $engine->updateStatus($fulfillment, [
            'status' => FulfillmentStatus::Shipped->value,
        ]);
    }

    public function test_order_has_one_fulfillment_relationship(): void
    {
        $order = $this->makePaidOrderWithProduct(['fulfillment_source' => 'buy_from_tz']);
        $fulfillment = app(FulfillmentEngine::class)->createForOrder($order);

        $this->assertTrue($order->fresh()->fulfillment->is($fulfillment));
        $this->assertTrue($fulfillment->order->is($order));
    }

    public function test_admin_api_create_show_and_status_update(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $order = $this->makePaidOrderWithProduct([
            'fulfillment_source' => 'imported_from_china',
        ]);

        $created = $this->postJson("/api/v1/admin/fulfillments/create/{$order->id}")
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.strategy', 'china')
            ->assertJsonPath('data.status', 'pending');

        $id = $created->json('data.id');

        $this->getJson("/api/v1/admin/fulfillments/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.order.order_number', $order->order_number);

        $this->patchJson("/api/v1/admin/fulfillments/{$id}/status", [
            'status' => 'processing',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'processing');

        $this->getJson('/api/v1/admin/fulfillments')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_guest_cannot_access_fulfillment_api(): void
    {
        $order = $this->makePaidOrderWithProduct(['fulfillment_source' => 'buy_from_tz']);

        $this->postJson("/api/v1/admin/fulfillments/create/{$order->id}")
            ->assertUnauthorized();
    }

    public function test_customer_cannot_access_fulfillment_api(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $order = $this->makePaidOrderWithProduct(['fulfillment_source' => 'buy_from_tz']);

        $this->postJson("/api/v1/admin/fulfillments/create/{$order->id}")
            ->assertUnauthorized();
    }

    public function test_successful_payment_auto_creates_fulfillment(): void
    {
        $order = $this->makePaidOrderWithProduct([
            'fulfillment_source' => 'imported_from_china',
        ]);

        // Simulate payment completion path without gateway HTTP.
        $order->update([
            'status' => OrderStatus::PendingPayment,
            'paid_at' => null,
        ]);

        $transaction = \App\Models\PaymentTransaction::factory()->processing()->create([
            'order_id' => $order->id,
            'amount' => $order->total,
            'currency' => 'TZS',
        ]);

        $result = new \App\Services\Payments\Orchestration\DTOs\PaymentProviderResult(
            ok: true,
            status: \App\Enums\PaymentTransactionStatus::Successful,
            providerReference: 'SESSION-FUL-1',
            externalTransactionId: 'TXN-FUL-1',
            message: 'Verified',
        );

        app(\App\Services\Payments\Orchestration\PaymentTransactionCompletionService::class)
            ->applyResult($transaction, $result);

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->fulfillment);
        $this->assertSame(FulfillmentStrategy::China, $order->fulfillment->strategy);
        $this->assertSame(FulfillmentStatus::Pending, $order->fulfillment->status);
    }
}

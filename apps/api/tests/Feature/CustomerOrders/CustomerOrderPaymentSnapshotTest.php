<?php

namespace Tests\Feature\CustomerOrders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerOrderPaymentSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_exposes_orchestrator_payment_without_payments_row(): void
    {
        $user = User::factory()->create();
        $paidAt = now()->subHour();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Processing,
            'paid_at' => $paidAt,
            'total' => 125000,
            'currency' => 'TZS',
        ]);

        PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
            'merchant_reference' => 'COTZ-PAY-NMB-001',
            'amount' => 125000,
            'currency' => 'TZS',
            'status' => PaymentTransactionStatus::Successful,
            'initiated_at' => now()->subHours(2),
            'completed_at' => $paidAt,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.payment.payment_status', PaymentStatus::Paid->value)
            ->assertJsonPath('data.payment.payment_method', PaymentProvider::Nmb->value)
            ->assertJsonPath('data.payment.provider', PaymentProvider::Nmb->value)
            ->assertJsonPath('data.payment.reference', 'COTZ-PAY-NMB-001')
            ->assertJsonPath('data.payment.amount', '125000.00')
            ->assertJsonPath('data.payment.currency', 'TZS')
            ->assertJsonStructure([
                'data' => [
                    'payment' => [
                        'paid_at',
                        'initiated_at',
                    ],
                ],
            ]);
    }

    public function test_detail_exposes_legacy_payment_fields(): void
    {
        $user = User::factory()->create();
        $paidAt = now()->subMinutes(30);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Confirmed,
            'paid_at' => $paidAt,
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => PaymentMethod::Mpesa,
            'status' => PaymentStatus::Paid,
            'reference' => 'PAY-LEGACY-001',
            'amount' => 88000,
            'currency' => 'TZS',
            'paid_at' => $paidAt,
            'initiated_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.payment.payment_status', PaymentStatus::Paid->value)
            ->assertJsonPath('data.payment.payment_method', PaymentMethod::Mpesa->value)
            ->assertJsonPath('data.payment.reference', 'PAY-LEGACY-001')
            ->assertJsonPath('data.payment.provider', null)
            ->assertJsonPath('data.payment.amount', '88000.00')
            ->assertJsonPath('data.payment.currency', 'TZS');
    }

    public function test_successful_orchestrator_snapshot_takes_priority_over_legacy_payment_row(): void
    {
        $user = User::factory()->create();
        $completedAt = now()->subMinutes(10);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Processing,
            'paid_at' => $completedAt,
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => PaymentMethod::Mpesa,
            'status' => PaymentStatus::Initiated,
            'reference' => 'PAY-STALE-001',
            'created_at' => now()->subHour(),
        ]);

        PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
            'merchant_reference' => 'COTZ-PAY-NMB-002',
            'status' => PaymentTransactionStatus::Successful,
            'completed_at' => $completedAt,
            'created_at' => now()->subMinutes(15),
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.payment.payment_method', PaymentProvider::Nmb->value)
            ->assertJsonPath('data.payment.reference', 'COTZ-PAY-NMB-002');
    }

    public function test_list_and_detail_payment_status_match(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Delivered,
            'paid_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($user);

        $listStatus = $this->getJson('/api/v1/orders')
            ->assertOk()
            ->json('data.0.payment_status');

        $detailStatus = $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->json('data.payment.payment_status');

        $this->assertSame($listStatus, $detailStatus);
        $this->assertSame(PaymentStatus::Paid->value, $detailStatus);
    }

    public function test_list_and_detail_payment_status_match_with_latest_payment_row(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::PendingPayment,
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'status' => PaymentStatus::Initiated,
            'created_at' => now()->subMinute(),
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'status' => PaymentStatus::Pending,
            'created_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($user);

        $listStatus = $this->getJson('/api/v1/orders')
            ->assertOk()
            ->json('data.0.payment_status');

        $detailStatus = $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->json('data.payment.payment_status');

        $this->assertSame($listStatus, $detailStatus);
    }

    public function test_list_and_detail_payment_status_match_with_processing_transaction(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::PendingPayment,
        ]);

        PaymentTransaction::factory()->processing()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
            'merchant_reference' => 'COTZ-PAY-PROC-001',
            'amount' => 55000,
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'status' => PaymentStatus::Pending,
            'created_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($user);

        $listStatus = $this->getJson('/api/v1/orders')
            ->assertOk()
            ->json('data.0.payment_status');

        $detailStatus = $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->json('data.payment.payment_status');

        $this->assertSame(PaymentStatus::Initiated->value, $listStatus);
        $this->assertSame($listStatus, $detailStatus);
        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertJsonPath('data.payment.payment_method', PaymentProvider::Nmb->value)
            ->assertJsonPath('data.payment.reference', 'COTZ-PAY-PROC-001');
    }

    public function test_successful_transaction_status_overrides_stale_legacy_payment(): void
    {
        $user = User::factory()->create();
        $completedAt = now()->subMinutes(5);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Processing,
            'paid_at' => $completedAt,
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'status' => PaymentStatus::Initiated,
            'created_at' => now()->subHour(),
        ]);

        PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
            'status' => PaymentTransactionStatus::Successful,
            'completed_at' => $completedAt,
        ]);

        Sanctum::actingAs($user);

        $listStatus = $this->getJson('/api/v1/orders')
            ->assertOk()
            ->json('data.0.payment_status');

        $detailStatus = $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->json('data.payment.payment_status');

        $this->assertSame(PaymentStatus::Paid->value, $listStatus);
        $this->assertSame($listStatus, $detailStatus);
    }
}

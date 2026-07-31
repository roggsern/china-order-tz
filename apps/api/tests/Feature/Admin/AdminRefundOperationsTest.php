<?php

namespace Tests\Feature\Admin;

use App\Enums\ActivityEventType;
use App\Enums\NotificationEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundTransactionStatus;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RefundTransaction;
use App\Models\User;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminRefundOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NotificationTemplateSeeder::class);
    }

    /**
     * @return array{user: User, order: Order, payment: Payment}
     */
    private function paidOrder(float $total = 100000.0): array
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Confirmed,
            'total' => $total,
            'paid_at' => now()->subHour(),
        ]);
        $payment = Payment::factory()->paid()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'amount' => $total,
            'currency' => 'TZS',
        ]);

        return compact('user', 'order', 'payment');
    }

    public function test_guest_and_unauthorized_admin_cannot_access_refunds(): void
    {
        $this->getJson('/api/v1/admin/refunds')->assertUnauthorized();

        Sanctum::actingAs(Admin::factory()->withoutPermissions()->create());
        $this->getJson('/api/v1/admin/refunds')->assertForbidden();
    }

    public function test_view_permission_can_list_and_show_but_not_mutate(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->paidOrder();

        $manager = Admin::factory()->withPermissions([
            AdminPermissions::REFUNDS_MANAGE,
        ])->create();
        Sanctum::actingAs($manager);

        $refundId = $this->postJson('/api/v1/admin/refunds', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'amount' => 50000,
            'reason' => 'Partial goodwill',
        ])->assertCreated()->json('data.id');

        $viewer = Admin::factory()->withPermissions([
            AdminPermissions::REFUNDS_VIEW,
        ])->create();
        Sanctum::actingAs($viewer);

        $this->getJson('/api/v1/admin/refunds')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['id' => $refundId]);

        $this->getJson("/api/v1/admin/refunds/{$refundId}")
            ->assertOk()
            ->assertJsonPath('data.status', RefundTransactionStatus::Requested->value);

        $this->postJson("/api/v1/admin/refunds/{$refundId}/approve")->assertForbidden();
        $this->postJson("/api/v1/admin/refunds/{$refundId}/process")->assertForbidden();
    }

    public function test_create_refund_duplicate_prevention_and_partial_full_amounts(): void
    {
        ['user' => $user, 'order' => $order, 'payment' => $payment] = $this->paidOrder(100000);

        $admin = Admin::factory()->withPermissions([
            AdminPermissions::REFUNDS_VIEW,
            AdminPermissions::REFUNDS_MANAGE,
            AdminPermissions::REFUNDS_APPROVE,
        ])->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/refunds', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'amount' => 40000,
            'reason' => 'Partial refund',
        ])->assertCreated()
            ->assertJsonPath('data.status', RefundTransactionStatus::Requested->value)
            ->assertJsonPath('data.amount', '40000.00');

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::RefundCreated->value,
            'subject_type' => RefundTransaction::class,
        ]);

        $this->assertDatabaseHas('notifications', [
            'customer_id' => $user->id,
            'event_type' => NotificationEventType::RefundRequested->value,
        ]);

        $this->postJson('/api/v1/admin/refunds', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'amount' => 10000,
            'reason' => 'Duplicate attempt',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['refund']);

        $partialId = RefundTransaction::query()->where('order_id', $order->id)->value('id');

        $this->postJson("/api/v1/admin/refunds/{$partialId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', RefundTransactionStatus::Approved->value);

        $this->postJson("/api/v1/admin/refunds/{$partialId}/process")
            ->assertOk()
            ->assertJsonPath('data.status', RefundTransactionStatus::Completed->value);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::RefundApproved->value,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::RefundProcessed->value,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::RefundCompleted->value,
        ]);

        $this->postJson('/api/v1/admin/refunds', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'amount' => 60000,
            'reason' => 'Remaining balance',
        ])->assertCreated();

        $fullId = RefundTransaction::query()
            ->where('order_id', $order->id)
            ->where('status', RefundTransactionStatus::Requested->value)
            ->value('id');

        $this->postJson("/api/v1/admin/refunds/{$fullId}/approve")->assertOk();
        $this->postJson("/api/v1/admin/refunds/{$fullId}/process")
            ->assertOk()
            ->assertJsonPath('data.status', RefundTransactionStatus::Completed->value);

        $this->assertSame(
            2,
            RefundTransaction::query()
                ->where('order_id', $order->id)
                ->where('status', RefundTransactionStatus::Completed->value)
                ->count(),
        );
    }

    public function test_cannot_refund_cancelled_or_unpaid_orders(): void
    {
        $admin = Admin::factory()->withPermissions([
            AdminPermissions::REFUNDS_MANAGE,
        ])->create();
        Sanctum::actingAs($admin);

        $cancelled = Order::factory()->create([
            'status' => OrderStatus::Cancelled,
            'paid_at' => null,
        ]);

        $this->postJson('/api/v1/admin/refunds', [
            'order_id' => $cancelled->id,
            'amount' => 1000,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['order_id']);

        $unpaid = Order::factory()->create([
            'status' => OrderStatus::PendingPayment,
            'paid_at' => null,
        ]);

        $this->postJson('/api/v1/admin/refunds', [
            'order_id' => $unpaid->id,
            'amount' => 1000,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['order_id']);
    }

    public function test_reject_refund_requires_approve_permission_and_records_audit(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->paidOrder();

        $manager = Admin::factory()->withPermissions([
            AdminPermissions::REFUNDS_MANAGE,
        ])->create();
        Sanctum::actingAs($manager);

        $refundId = $this->postJson('/api/v1/admin/refunds', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'amount' => 25000,
            'reason' => 'Test reject',
        ])->json('data.id');

        Sanctum::actingAs(Admin::factory()->withPermissions([
            AdminPermissions::REFUNDS_VIEW,
        ])->create());

        $this->postJson("/api/v1/admin/refunds/{$refundId}/reject", [
            'reason' => 'Not eligible',
        ])->assertForbidden();

        $approver = Admin::factory()->withPermissions([
            AdminPermissions::REFUNDS_APPROVE,
        ])->create();
        Sanctum::actingAs($approver);

        $this->postJson("/api/v1/admin/refunds/{$refundId}/reject", [
            'reason' => 'Not eligible',
        ])->assertOk()
            ->assertJsonPath('data.status', RefundTransactionStatus::Rejected->value);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::RefundRejected->value,
        ]);

        $this->assertDatabaseHas('notifications', [
            'event_type' => NotificationEventType::RefundRejected->value,
        ]);
    }

    public function test_amount_cannot_exceed_paid_total(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->paidOrder(50000);

        Sanctum::actingAs(Admin::factory()->withPermissions([
            AdminPermissions::REFUNDS_MANAGE,
        ])->create());

        $this->postJson('/api/v1/admin/refunds', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'amount' => 75000,
            'reason' => 'Too much',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_nmb_provider_placeholder_marks_refund_failed(): void
    {
        ['order' => $order] = $this->paidOrder();

        Sanctum::actingAs(Admin::factory()->withPermissions([
            AdminPermissions::REFUNDS_MANAGE,
            AdminPermissions::REFUNDS_APPROVE,
        ])->create());

        Payment::factory()->paid()->nmb()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'amount' => 100000,
        ]);

        $refundId = $this->postJson('/api/v1/admin/refunds', [
            'order_id' => $order->id,
            'amount' => 10000,
            'method' => 'nmb',
            'reason' => 'PSP refund',
        ])->json('data.id');

        $this->postJson("/api/v1/admin/refunds/{$refundId}/approve")->assertOk();
        $this->postJson("/api/v1/admin/refunds/{$refundId}/process")
            ->assertOk()
            ->assertJsonPath('data.status', RefundTransactionStatus::Failed->value);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::RefundFailed->value,
        ]);

        $this->assertTrue(
            Notification::query()
                ->where('event_type', NotificationEventType::RefundFailed->value)
                ->exists(),
        );
    }
}

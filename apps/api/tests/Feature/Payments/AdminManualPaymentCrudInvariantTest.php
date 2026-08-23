<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\Audit\PaymentConfirmed;
use App\Models\Admin;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminManualPaymentCrudInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_raw_crud_cannot_transition_initiated_to_paid(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->officePayment(PaymentStatus::Initiated);
        Sanctum::actingAs($this->financeAdmin());

        $this->putJson("/api/v1/admin/payments/{$payment->id}", $this->payload($order, $payment, [
            'status' => PaymentStatus::Paid->value,
        ]))->assertUnprocessable()
            ->assertJsonPath('success', false);

        $payment = $payment->fresh();
        $this->assertSame(PaymentStatus::Initiated, $payment->status);
        $this->assertNull($payment->paid_at);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertSame(0, Fulfillment::query()->where('order_id', $order->id)->count());
    }

    public function test_raw_crud_cannot_transition_pending_to_paid(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->officePayment(PaymentStatus::Pending);
        Sanctum::actingAs($this->financeAdmin());

        $this->putJson("/api/v1/admin/payments/{$payment->id}", $this->payload($order, $payment, [
            'status' => PaymentStatus::Paid->value,
        ]))->assertUnprocessable();

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->paid_at);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_raw_crud_cannot_fabricate_paid_at(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->officePayment(PaymentStatus::Initiated);
        Sanctum::actingAs($this->financeAdmin());

        $this->putJson("/api/v1/admin/payments/{$payment->id}", $this->payload($order, $payment, [
            'status' => PaymentStatus::Initiated->value,
            'paid_at' => now()->toIso8601String(),
        ]))->assertUnprocessable();

        $this->assertNull($payment->fresh()->paid_at);
        $this->assertSame(PaymentStatus::Initiated, $payment->fresh()->status);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_raw_crud_cannot_create_a_paid_payment(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, [
            'status' => OrderStatus::PendingPayment,
            'total' => 45000,
        ]);
        Sanctum::actingAs($this->financeAdmin());

        $this->postJson('/api/v1/admin/payments', [
            'order_id' => $order->id,
            'amount' => 45000,
            'currency' => 'TZS',
            'status' => PaymentStatus::Paid->value,
            'payment_method' => PaymentMethod::Cash->value,
            'paid_at' => now()->toIso8601String(),
        ])->assertUnprocessable();

        $this->assertSame(0, Payment::query()->where('order_id', $order->id)->count());
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_non_paid_permitted_edits_still_work(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->officePayment(PaymentStatus::Initiated);
        Sanctum::actingAs($this->financeAdmin());

        $this->putJson("/api/v1/admin/payments/{$payment->id}", $this->payload($order, $payment, [
            'status' => PaymentStatus::Failed->value,
            'transaction_reference' => 'OFFICE-NOTE-1',
        ]))->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Failed->value)
            ->assertJsonPath('data.reference', 'OFFICE-NOTE-1');

        $payment = $payment->fresh();
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertNull($payment->paid_at);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_already_paid_historical_row_remains_edit_safe(): void
    {
        $paidAt = now()->subDay()->seconds(0);
        ['order' => $order, 'payment' => $payment] = $this->officePayment(PaymentStatus::Paid, [
            'paid_at' => $paidAt,
        ]);
        $order->forceFill([
            'status' => OrderStatus::Paid,
            'paid_at' => $paidAt,
        ])->save();

        Sanctum::actingAs($this->financeAdmin());

        $this->putJson("/api/v1/admin/payments/{$payment->id}", $this->payload($order, $payment, [
            'status' => PaymentStatus::Paid->value,
            'transaction_reference' => 'HIST-REF',
            'paid_at' => now()->toIso8601String(),
        ]))->assertOk();

        $payment = $payment->fresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame('HIST-REF', $payment->reference);
        $this->assertSame($paidAt->toDateTimeString(), $payment->paid_at?->toDateTimeString());
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame($paidAt->toDateTimeString(), $order->fresh()->paid_at?->toDateTimeString());
    }

    public function test_already_paid_row_cannot_be_unpaid_through_crud(): void
    {
        $paidAt = now()->subHour();
        ['order' => $order, 'payment' => $payment] = $this->officePayment(PaymentStatus::Paid, [
            'paid_at' => $paidAt,
        ]);
        $order->forceFill(['status' => OrderStatus::Paid, 'paid_at' => $paidAt])->save();

        Sanctum::actingAs($this->financeAdmin());
        $this->putJson("/api/v1/admin/payments/{$payment->id}", $this->payload($order, $payment, [
            'status' => PaymentStatus::Pending->value,
        ]))->assertUnprocessable();

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->paid_at);
    }

    public function test_view_permission_still_cannot_mutate_payments(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->officePayment(PaymentStatus::Initiated);
        Sanctum::actingAs(Admin::factory()->withPermissions([AdminPermissions::PAYMENTS_VIEW])->create());

        $this->putJson("/api/v1/admin/payments/{$payment->id}", $this->payload($order, $payment, [
            'status' => PaymentStatus::Failed->value,
        ]))->assertForbidden();

        $this->assertSame(PaymentStatus::Initiated, $payment->fresh()->status);
    }

    public function test_super_admin_is_still_bound_by_paid_invariant(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->officePayment(PaymentStatus::Initiated);
        Sanctum::actingAs(Admin::factory()->superAdmin()->create());

        $this->putJson("/api/v1/admin/payments/{$payment->id}", $this->payload($order, $payment, [
            'status' => PaymentStatus::Paid->value,
        ]))->assertUnprocessable();

        $this->assertSame(PaymentStatus::Initiated, $payment->fresh()->status);
    }

    public function test_office_confirmation_still_pays_after_crud_guard(): void
    {
        Event::fake([PaymentConfirmed::class]);
        ['order' => $order, 'payment' => $payment] = $this->officePayment(PaymentStatus::Initiated);
        Sanctum::actingAs(Admin::factory()->withPermissions([AdminPermissions::ORDERS_MARK_PAID])->create());

        $this->patchJson("/api/v1/admin/orders/{$order->id}/pay")->assertOk();

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->paid_at);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        Event::assertDispatchedTimes(PaymentConfirmed::class, 1);
    }

    /**
     * @param  array<string, mixed>  $paymentOverrides
     * @return array{order: Order, payment: Payment}
     */
    private function officePayment(PaymentStatus $status, array $paymentOverrides = []): array
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, [
            'status' => OrderStatus::PendingPayment,
            'total' => 45000,
            'currency' => 'TZS',
        ]);
        $payment = Payment::factory()->create(array_merge([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => PaymentMethod::Cash,
            'status' => $status,
            'amount' => 45000,
            'currency' => 'TZS',
            'paid_at' => null,
        ], $paymentOverrides));

        return ['order' => $order, 'payment' => $payment];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Order $order, Payment $payment, array $overrides = []): array
    {
        return array_merge([
            'order_id' => $order->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status instanceof PaymentStatus
                ? $payment->status->value
                : (string) $payment->status,
            'payment_method' => $payment->method instanceof PaymentMethod
                ? $payment->method->value
                : (string) $payment->method,
        ], $overrides);
    }

    private function financeAdmin(): Admin
    {
        return Admin::factory()->withPermissions([
            AdminPermissions::PAYMENTS_MANAGE_MANUAL,
        ])->create();
    }
}

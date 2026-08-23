<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentTransactionStatus;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Payments\Orchestration\DTOs\PaymentProviderResult;
use App\Services\Payments\Orchestration\PaymentTransactionCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CancelledOrderLatePaymentSuccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_late_successful_collection_does_not_mark_cancelled_order_paid(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 45000]);

        $transaction = PaymentTransaction::factory()->processing()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
            'amount' => 45000,
        ]);

        $order->forceFill([
            'status' => OrderStatus::Cancelled,
            'paid_at' => null,
            'cancelled_at' => now(),
        ])->save();

        $completed = app(PaymentTransactionCompletionService::class)->applyResult(
            $transaction,
            new PaymentProviderResult(
                ok: true,
                status: PaymentTransactionStatus::Successful,
                providerReference: $transaction->provider_reference,
                externalTransactionId: 'EXT-CANCEL-RACE-1',
            ),
        );

        $this->assertSame(PaymentTransactionStatus::Successful, $completed->status);
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
    }

    public function test_order_cancellation_leaves_processing_transaction_for_reconciliation(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 45000]);

        $transaction = PaymentTransaction::factory()->processing()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Snippe,
            'amount' => 45000,
        ]);

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'Changed mind'])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value);

        $this->assertSame(PaymentTransactionStatus::Processing, $transaction->fresh()->status);
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
    }
}

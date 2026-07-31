<?php

namespace Tests\Feature\Payments;

use App\Enums\NotificationEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Notifications\NotificationPlatform;
use App\Services\Orders\CustomerOrderPaymentStatusResolver;
use App\Services\Payments\Orchestration\DTOs\PaymentProviderResult;
use App\Services\Payments\Orchestration\PaymentTransactionCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class PaymentReturnResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.nmb.enabled' => true,
            'services.nmb.webhook.require_signature' => false,
            'payments.orchestrator.default_provider' => 'nmb',
        ]);
    }

    public function test_resolve_return_context_by_merchant_reference_returns_authoritative_transaction(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 10000]);

        $transaction = PaymentTransaction::factory()->processing()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
            'merchant_reference' => 'COTZ-PAY-20260730-000010',
            'amount' => 10000,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/payments/return-context?merchant_reference=COTZ-PAY-20260730-000010')
            ->assertOk()
            ->assertJsonPath('data.id', $transaction->id)
            ->assertJsonPath('data.status', PaymentTransactionStatus::Processing->value);
    }

    public function test_resolve_return_context_prefers_successful_transaction_for_paid_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
            'total' => 10000,
        ]);

        PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
            'merchant_reference' => 'COTZ-PAY-20260730-000011',
            'status' => PaymentTransactionStatus::Failed,
            'amount' => 10000,
            'created_at' => now()->subHour(),
        ]);

        $successful = PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
            'merchant_reference' => 'COTZ-PAY-20260730-000012',
            'status' => PaymentTransactionStatus::Successful,
            'amount' => 10000,
            'completed_at' => now(),
            'created_at' => now()->subMinutes(10),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/payments/return-context?order_id='.$order->id)
            ->assertOk()
            ->assertJsonPath('data.id', $successful->id)
            ->assertJsonPath('data.status', PaymentTransactionStatus::Successful->value);
    }

    public function test_notification_confirmed_and_payment_status_resolver_stay_consistent_after_success(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 15000]);

        $transaction = PaymentTransaction::factory()->processing()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
            'merchant_reference' => 'COTZ-PAY-20260730-000013',
            'amount' => 15000,
        ]);

        $notifications = Mockery::mock(NotificationPlatform::class);
        $notifications->shouldReceive('notifyCustomer')
            ->once()
            ->with(
                NotificationEventType::PaymentConfirmed,
                Mockery::on(fn ($subject) => $subject instanceof User && $subject->is($user)),
                Mockery::type('array'),
            );
        $this->app->instance(NotificationPlatform::class, $notifications);

        app(PaymentTransactionCompletionService::class)->applyResult(
            $transaction,
            new PaymentProviderResult(
                ok: true,
                status: PaymentTransactionStatus::Successful,
                providerReference: $transaction->provider_reference,
                externalTransactionId: 'EXT-RETURN-001',
            ),
        );

        $order->refresh();
        $transaction->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(PaymentTransactionStatus::Successful, $transaction->status);

        $order->load(['paymentTransactions', 'payments']);
        $this->assertSame(
            PaymentStatus::Paid->value,
            app(CustomerOrderPaymentStatusResolver::class)->resolve($order),
        );

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/payments/return-context?merchant_reference=COTZ-PAY-20260730-000013')
            ->assertOk()
            ->assertJsonPath('data.status', PaymentTransactionStatus::Successful->value);

        $this->getJson('/api/v1/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.payment.payment_status', PaymentStatus::Paid->value);
    }

    public function test_processing_transaction_does_not_resolve_as_paid(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 12000]);

        PaymentTransaction::factory()->processing()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
            'merchant_reference' => 'COTZ-PAY-20260730-000014',
            'amount' => 12000,
        ]);

        $order->load('paymentTransactions');

        $this->assertSame(
            PaymentStatus::Initiated->value,
            app(CustomerOrderPaymentStatusResolver::class)->resolve($order),
        );
    }
}

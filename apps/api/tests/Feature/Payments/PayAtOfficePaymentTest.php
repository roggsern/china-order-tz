<?php

namespace Tests\Feature\Payments;

use App\Enums\CommerceChannelCode;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\Audit\PaymentConfirmed;
use App\Models\Admin;
use App\Models\ChinaCommercialStock;
use App\Models\CommerceChannel;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProfitRecord;
use App\Models\User;
use App\Models\VariantInventory;
use App\Services\Settings\SettingsService;
use App\Support\Admin\AdminPermissions;
use Database\Factories\Support\CatalogCartFixture;
use Database\Seeders\CommerceChannelSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayAtOfficePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        $this->seed(CommerceChannelSeeder::class);
        Cache::flush();
    }

    public function test_cash_remains_disabled_and_not_selectable_by_default(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $cash = collect($this->getJson('/api/v1/payments/methods')->assertOk()->json('data.methods'))
            ->firstWhere('code', 'cash');

        $this->assertNotNull($cash);
        $this->assertFalse($cash['enabled']);
        $this->assertFalse($cash['selectable']);
    }

    public function test_prepare_leaves_order_unpaid_and_payment_initiated(): void
    {
        $this->enableCash();
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 45000, 'currency' => 'TZS']);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/orders/{$order->id}/payments", [
            'payment_method' => PaymentMethod::Cash->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.payment_method', 'cash')
            ->assertJsonPath('data.status', PaymentStatus::Initiated->value);

        $order = $order->fresh();
        $payment = Payment::query()->where('order_id', $order->id)->first();

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertNull($order->paid_at);
        $this->assertNotNull($payment);
        $this->assertSame(PaymentStatus::Initiated, $payment->status);
        $this->assertNull($payment->paid_at);
        $this->assertSame(0, Fulfillment::query()->where('order_id', $order->id)->count());
    }

    public function test_successful_confirmation_pays_payment_and_order_once(): void
    {
        Event::fake([PaymentConfirmed::class]);

        ['order' => $order, 'payment' => $payment, 'inventory' => $inventory] = $this->createTzOfficeOrder();
        $admin = Admin::factory()->withPermissions([AdminPermissions::ORDERS_MARK_PAID])->create();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/orders/{$order->id}/pay", [
            'reference' => 'OFFICE-RECEIPT-1',
            'note' => 'Cash received at DSM office',
        ])->assertOk();

        $payment = $payment->fresh();
        $order = $order->fresh(['statusHistory']);

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('45000.00', (string) $payment->amount);
        $this->assertSame('TZS', $payment->currency);
        $this->assertSame($admin->id, $payment->metadata['office_confirmation']['confirmed_by_admin_id'] ?? null);
        $this->assertSame('OFFICE-RECEIPT-1', $payment->metadata['office_confirmation']['reference'] ?? null);
        $this->assertSame('Cash received at DSM office', $payment->metadata['office_confirmation']['note'] ?? null);
        $paidHistory = $order->statusHistory->first(
            fn ($row) => ($row->new_status instanceof OrderStatus
                ? $row->new_status
                : OrderStatus::tryFrom((string) $row->new_status)) === OrderStatus::Paid,
        );
        $this->assertSame($admin->id, $paidHistory?->changed_by_admin_id);
        $this->assertSame('admin_pay', $paidHistory?->source);
        $this->assertSame(8, (int) $inventory->fresh()->on_hand);
        $this->assertSame(1, Fulfillment::query()->where('order_id', $order->id)->count());
        Event::assertDispatchedTimes(PaymentConfirmed::class, 1);

        $this->patchJson("/api/v1/admin/orders/{$order->id}/pay")->assertOk();

        $this->assertSame(8, (int) $inventory->fresh()->on_hand);
        $this->assertSame(1, Fulfillment::query()->where('order_id', $order->id)->count());
        $this->assertSame(1, Payment::query()->where('order_id', $order->id)->count());
        $this->assertLessThanOrEqual(1, ProfitRecord::query()->where('order_id', $order->id)->count());
        Event::assertDispatchedTimes(PaymentConfirmed::class, 1);
    }

    public function test_already_paid_office_order_is_idempotent(): void
    {
        Event::fake([PaymentConfirmed::class]);
        ['order' => $order] = $this->createTzOfficeOrder();
        $admin = Admin::factory()->withPermissions([AdminPermissions::ORDERS_MARK_PAID])->create();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/orders/{$order->id}/pay")->assertOk();
        $this->patchJson("/api/v1/admin/orders/{$order->id}/pay")->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Paid->value);
    }

    public function test_cancelled_order_is_rejected(): void
    {
        ['order' => $order] = $this->createTzOfficeOrder(['status' => OrderStatus::Cancelled]);
        Sanctum::actingAs(Admin::factory()->withPermissions([AdminPermissions::ORDERS_MARK_PAID])->create());

        $this->patchJson("/api/v1/admin/orders/{$order->id}/pay")
            ->assertUnprocessable();
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Initiated, $order->payments()->first()->status);
    }

    public function test_refunded_and_refund_pending_orders_are_rejected(): void
    {
        $admin = Admin::factory()->withPermissions([AdminPermissions::ORDERS_MARK_PAID])->create();
        Sanctum::actingAs($admin);

        foreach ([OrderStatus::Refunded, OrderStatus::RefundPending] as $status) {
            ['order' => $order] = $this->createTzOfficeOrder(['status' => $status]);
            $this->patchJson("/api/v1/admin/orders/{$order->id}/pay")->assertUnprocessable();
            $this->assertSame($status, $order->fresh()->status);
        }
    }

    public function test_failed_or_expired_office_payment_is_rejected(): void
    {
        Sanctum::actingAs(Admin::factory()->withPermissions([AdminPermissions::ORDERS_MARK_PAID])->create());

        foreach ([PaymentStatus::Failed, PaymentStatus::Expired] as $status) {
            ['order' => $order, 'payment' => $payment] = $this->createTzOfficeOrder();
            $payment->forceFill(['status' => $status])->save();

            $this->patchJson("/api/v1/admin/orders/{$order->id}/pay")->assertUnprocessable();
            $this->assertSame($status, $payment->fresh()->status);
            $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        }
    }

    public function test_confirmation_does_not_touch_another_orders_cash_payment(): void
    {
        ['order' => $other, 'payment' => $otherPayment] = $this->createTzOfficeOrder();
        ['order' => $order, 'payment' => $payment] = $this->createTzOfficeOrder();

        Sanctum::actingAs(Admin::factory()->withPermissions([AdminPermissions::ORDERS_MARK_PAID])->create());
        $this->patchJson("/api/v1/admin/orders/{$order->id}/pay")->assertOk();

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Initiated, $otherPayment->fresh()->status);
        $this->assertSame(OrderStatus::PendingPayment, $other->fresh()->status);
    }

    public function test_wrong_payment_method_is_rejected(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, [
            'status' => OrderStatus::PendingPayment,
            'total' => 45000,
        ]);
        Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => PaymentMethod::BankTransfer,
            'status' => PaymentStatus::Initiated,
            'amount' => 45000,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs(Admin::factory()->withPermissions([AdminPermissions::ORDERS_MARK_PAID])->create());
        $this->patchJson("/api/v1/admin/orders/{$order->id}/pay")->assertUnprocessable();
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_amount_mismatch_is_rejected(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->createTzOfficeOrder();
        $payment->forceFill(['amount' => 1000])->save();

        Sanctum::actingAs(Admin::factory()->withPermissions([AdminPermissions::ORDERS_MARK_PAID])->create());
        $this->patchJson("/api/v1/admin/orders/{$order->id}/pay")->assertUnprocessable();
        $this->assertSame(PaymentStatus::Initiated, $payment->fresh()->status);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_unauthorized_admin_is_rejected(): void
    {
        ['order' => $order] = $this->createTzOfficeOrder();
        Sanctum::actingAs(Admin::factory()->withPermissions([AdminPermissions::ORDERS_VIEW])->create());

        $this->patchJson("/api/v1/admin/orders/{$order->id}/pay")->assertForbidden();
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_super_admin_can_confirm(): void
    {
        ['order' => $order] = $this->createTzOfficeOrder();
        Sanctum::actingAs(Admin::factory()->superAdmin()->create());

        $this->patchJson("/api/v1/admin/orders/{$order->id}/pay")->assertOk();
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_china_import_confirmation_skips_local_inventory(): void
    {
        Event::fake([PaymentConfirmed::class]);
        ['order' => $order, 'stock' => $stock] = $this->createChinaOfficeOrder();
        Sanctum::actingAs(Admin::factory()->withPermissions([AdminPermissions::ORDERS_MARK_PAID])->create());

        $this->patchJson("/api/v1/admin/orders/{$order->id}/pay")->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(12, (int) $stock->fresh()->available_quantity);
        $this->assertSame(0, (int) $stock->fresh()->reserved_quantity);
        Event::assertDispatched(PaymentConfirmed::class);
    }

    public function test_tz_local_confirmation_commits_inventory(): void
    {
        ['order' => $order, 'inventory' => $inventory] = $this->createTzOfficeOrder();
        Sanctum::actingAs(Admin::factory()->withPermissions([AdminPermissions::ORDERS_MARK_PAID])->create());

        $this->patchJson("/api/v1/admin/orders/{$order->id}/pay")->assertOk();
        $this->assertSame(8, (int) $inventory->fresh()->on_hand);
    }

    /**
     * @param  array<string, mixed>  $orderOverrides
     * @return array{order: Order, payment: Payment, inventory: VariantInventory}
     */
    private function createTzOfficeOrder(array $orderOverrides = []): array
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(45000, 10);
        $channel = CommerceChannel::query()->where('code', CommerceChannelCode::TzLocal->value)->first();
        $order = $this->createPayableOrder($user, array_merge([
            'status' => OrderStatus::PendingPayment,
            'total' => 45000,
            'currency' => 'TZS',
            'commerce_channel_id' => $channel?->id,
            'commerce_channel_snapshot' => ['code' => CommerceChannelCode::TzLocal->value],
        ], $orderOverrides));
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => $variant->name,
            'sku' => $variant->sku,
            'quantity' => 2,
            'unit_price' => 22500,
            'line_total' => 45000,
            'total_price' => 45000,
            'currency' => 'TZS',
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Initiated,
            'amount' => 45000,
            'currency' => 'TZS',
        ]);
        $inventory = VariantInventory::query()->where('product_variant_id', $variant->id)->firstOrFail();

        return ['order' => $order->fresh(['payments', 'items']) ?? $order, 'payment' => $payment, 'inventory' => $inventory];
    }

    /**
     * @return array{order: Order, payment: Payment, stock: ChinaCommercialStock}
     */
    private function createChinaOfficeOrder(): array
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::chinaPurchasable(45000, 12);
        $channel = CommerceChannel::query()->where('code', CommerceChannelCode::ChinaImport->value)->firstOrFail();
        $order = $this->createPayableOrder($user, [
            'status' => OrderStatus::PendingPayment,
            'total' => 45000,
            'currency' => 'TZS',
            'commerce_channel_id' => $channel->id,
            'commerce_channel_snapshot' => [
                'id' => $channel->id,
                'code' => CommerceChannelCode::ChinaImport->value,
                'name' => $channel->name,
            ],
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => $variant->name,
            'sku' => $variant->sku,
            'quantity' => 1,
            'unit_price' => 45000,
            'line_total' => 45000,
            'total_price' => 45000,
            'currency' => 'TZS',
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Initiated,
            'amount' => 45000,
            'currency' => 'TZS',
        ]);
        $stock = ChinaCommercialStock::query()
            ->where('product_variant_id', $variant->id)
            ->firstOrFail();

        return ['order' => $order->fresh(['payments', 'items']) ?? $order, 'payment' => $payment, 'stock' => $stock];
    }

    private function enableCash(): void
    {
        app(SettingsService::class)->set('payments.enabled_methods', [
            'nmb' => true,
            'snippe' => false,
            'mpesa' => false,
            'card' => false,
            'cash' => true,
            'bank_transfer' => false,
        ]);
        Cache::flush();
    }
}

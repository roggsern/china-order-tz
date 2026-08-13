<?php

namespace Tests\Feature\Console;

use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\VariantPriceType;
use App\Models\CustomerProfile;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\User;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use App\Services\Production\AbandonedOrderCleanupManifest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionCleanupAbandonedOrdersCommandTest extends TestCase
{
    public function test_command_is_registered(): void
    {
        Artisan::call('list', ['--raw' => true]);

        $this->assertStringContainsString('production:cleanup-abandoned-orders', Artisan::output());
    }

    public function test_default_invocation_is_dry_run(): void
    {
        $fixture = $this->seedFixture();

        $this->artisan('production:cleanup-abandoned-orders', [
            '--keep-order' => $fixture['keep_order_number'],
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('DRY RUN — NO WRITES PERFORMED')
            ->expectsOutputToContain('PROTECTED ORDER')
            ->expectsOutputToContain($fixture['keep_order_number']);

        $this->assertSame(2, Order::query()->count());
        $this->assertDatabaseHas('orders', ['id' => $fixture['abandoned_order_id']]);
        $this->assertDatabaseHas('users', ['id' => $fixture['regina_user_id']]);
    }

    public function test_invalid_confirmation_blocks_writes(): void
    {
        $fixture = $this->seedFixture();

        $this->artisan('production:cleanup-abandoned-orders', [
            '--force' => true,
            '--confirm' => 'WRONG_PHRASE',
            '--keep-order' => $fixture['keep_order_number'],
        ])
            ->assertFailed()
            ->expectsOutputToContain('Destructive execution blocked');

        $this->assertSame(2, Order::query()->count());
        $this->assertDatabaseHas('orders', ['id' => $fixture['abandoned_order_id']]);
    }

    public function test_force_without_confirm_blocks_writes(): void
    {
        $fixture = $this->seedFixture();

        $this->artisan('production:cleanup-abandoned-orders', [
            '--force' => true,
            '--keep-order' => $fixture['keep_order_number'],
        ])
            ->assertFailed()
            ->expectsOutputToContain('Destructive execution blocked');

        $this->assertSame(2, Order::query()->count());
    }

    public function test_paid_order_candidate_aborts_entire_run(): void
    {
        $fixture = $this->seedFixture();

        Order::query()->whereKey($fixture['abandoned_order_id'])->update([
            'status' => OrderStatus::Paid->value,
        ]);

        $this->artisan('production:cleanup-abandoned-orders', [
            '--force' => true,
            '--confirm' => AbandonedOrderCleanupManifest::CONFIRMATION_PHRASE,
            '--keep-order' => $fixture['keep_order_number'],
        ])
            ->assertFailed()
            ->expectsOutputToContain('protected status');

        $this->assertSame(2, Order::query()->count());
        $this->assertDatabaseHas('orders', ['id' => $fixture['abandoned_order_id']]);
    }

    public function test_successful_payment_on_candidate_aborts_entire_run(): void
    {
        $fixture = $this->seedFixture();

        PaymentTransaction::factory()->create([
            'order_id' => $fixture['abandoned_order_id'],
            'status' => PaymentTransactionStatus::Successful,
            'merchant_reference' => 'COTZ-PAY-SHOULD-NOT-DELETE',
            'completed_at' => now(),
        ]);

        $this->artisan('production:cleanup-abandoned-orders', [
            '--force' => true,
            '--confirm' => AbandonedOrderCleanupManifest::CONFIRMATION_PHRASE,
            '--keep-order' => $fixture['keep_order_number'],
        ])
            ->assertFailed()
            ->expectsOutputToContain('protected status');

        $this->assertSame(2, Order::query()->count());
        $this->assertDatabaseHas('payment_transactions', [
            'merchant_reference' => 'COTZ-PAY-SHOULD-NOT-DELETE',
        ]);
    }

    public function test_cleanup_removes_abandoned_pending_order_and_processing_txn(): void
    {
        $fixture = $this->seedFixture();

        $productsBefore = Product::query()->count();
        $variantsBefore = ProductVariant::query()->count();
        $inventoryRowsBefore = VariantInventory::query()->count();
        $onHandBefore = (int) VariantInventory::query()->sum('on_hand');
        $reservedBefore = (int) VariantInventory::query()->sum('reserved');
        $usersBefore = User::query()->count();

        $this->artisan('production:cleanup-abandoned-orders', [
            '--force' => true,
            '--confirm' => AbandonedOrderCleanupManifest::CONFIRMATION_PHRASE,
            '--keep-order' => $fixture['keep_order_number'],
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Abandoned-order cleanup completed');

        $this->assertDatabaseMissing('orders', ['id' => $fixture['abandoned_order_id']]);
        $this->assertDatabaseMissing('order_items', ['order_id' => $fixture['abandoned_order_id']]);
        $this->assertDatabaseMissing('payment_transactions', ['id' => $fixture['abandoned_txn_id']]);
        $this->assertDatabaseMissing('payments', ['order_id' => $fixture['abandoned_order_id']]);
        $this->assertDatabaseMissing('fulfillments', ['order_id' => $fixture['abandoned_order_id']]);
        $this->assertDatabaseMissing('shipments', ['order_id' => $fixture['abandoned_order_id']]);

        $this->assertDatabaseHas('orders', [
            'id' => $fixture['keep_order_id'],
            'order_number' => $fixture['keep_order_number'],
            'status' => OrderStatus::Paid->value,
        ]);
        $this->assertDatabaseHas('payment_transactions', [
            'id' => $fixture['keep_txn_id'],
            'status' => PaymentTransactionStatus::Successful->value,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $fixture['regina_user_id'],
            'email' => 'malisaregine@gmail.com',
        ]);
        $this->assertDatabaseHas('customer_profiles', [
            'user_id' => $fixture['regina_user_id'],
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $fixture['qa_user_id'],
        ]);

        $this->assertSame($productsBefore, Product::query()->count());
        $this->assertSame($variantsBefore, ProductVariant::query()->count());
        $this->assertSame($inventoryRowsBefore, VariantInventory::query()->count());
        $this->assertSame($onHandBefore, (int) VariantInventory::query()->sum('on_hand'));
        $this->assertSame($reservedBefore, (int) VariantInventory::query()->sum('reserved'));
        $this->assertSame($usersBefore, User::query()->count());
        $this->assertSame(1, Order::query()->count());
    }

    public function test_second_cleanup_is_idempotent(): void
    {
        $fixture = $this->seedFixture();

        $this->artisan('production:cleanup-abandoned-orders', [
            '--force' => true,
            '--confirm' => AbandonedOrderCleanupManifest::CONFIRMATION_PHRASE,
            '--keep-order' => $fixture['keep_order_number'],
        ])->assertSuccessful();

        $this->artisan('production:cleanup-abandoned-orders', [
            '--force' => true,
            '--confirm' => AbandonedOrderCleanupManifest::CONFIRMATION_PHRASE,
            '--keep-order' => $fixture['keep_order_number'],
        ])->assertSuccessful();

        $this->artisan('production:cleanup-abandoned-orders', [
            '--keep-order' => $fixture['keep_order_number'],
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('DRY RUN — NO WRITES PERFORMED')
            ->expectsOutputToContain('(none)');

        $this->assertSame(1, Order::query()->count());
        $this->assertDatabaseHas('orders', ['id' => $fixture['keep_order_id']]);
        $this->assertDatabaseHas('users', ['email' => 'malisaregine@gmail.com']);
    }

    public function test_failure_rolls_back_database_changes(): void
    {
        $fixture = $this->seedFixture();
        config(['testing.fail_abandoned_order_cleanup_after' => 'orders']);

        $this->artisan('production:cleanup-abandoned-orders', [
            '--force' => true,
            '--confirm' => AbandonedOrderCleanupManifest::CONFIRMATION_PHRASE,
            '--keep-order' => $fixture['keep_order_number'],
        ])
            ->assertFailed()
            ->expectsOutputToContain('aborted and rolled back');

        $this->assertSame(2, Order::query()->count());
        $this->assertDatabaseHas('orders', ['id' => $fixture['abandoned_order_id']]);
        $this->assertDatabaseHas('payment_transactions', ['id' => $fixture['abandoned_txn_id']]);
        $this->assertDatabaseHas('orders', ['id' => $fixture['keep_order_id']]);
        $this->assertDatabaseHas('users', ['id' => $fixture['regina_user_id']]);
    }

    public function test_missing_keep_order_fails(): void
    {
        $this->artisan('production:cleanup-abandoned-orders')
            ->assertFailed()
            ->expectsOutputToContain('--keep-order');
    }

    public function test_protected_order_must_be_paid_with_successful_payment(): void
    {
        $fixture = $this->seedFixture();

        Order::query()->whereKey($fixture['keep_order_id'])->update([
            'status' => OrderStatus::PendingPayment->value,
        ]);

        $this->artisan('production:cleanup-abandoned-orders', [
            '--keep-order' => $fixture['keep_order_number'],
        ])
            ->assertFailed()
            ->expectsOutputToContain('status must be paid');
    }

    /**
     * @return array<string, string>
     */
    private function seedFixture(): array
    {
        $regina = User::factory()->create([
            'name' => 'Regina Malisa',
            'email' => 'malisaregine@gmail.com',
        ]);
        CustomerProfile::query()->create([
            'user_id' => $regina->id,
            'customer_code' => 'CUST-REGINA-001',
        ]);

        $qa = User::factory()->create([
            'email' => 'qa-abandoned@example.com',
        ]);
        CustomerProfile::query()->create([
            'user_id' => $qa->id,
            'customer_code' => 'CUST-QA-001',
        ]);

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 75000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => 25,
            'reserved' => 0,
            'reorder_level' => 1,
            'safety_stock' => 0,
            'is_active' => true,
        ]);

        $keepOrder = Order::factory()->create([
            'user_id' => $regina->id,
            'order_number' => AbandonedOrderCleanupManifest::DOCUMENTED_PROTECTED_ORDER_NUMBER,
            'status' => OrderStatus::Paid,
            'total' => 75000,
            'paid_at' => now()->subDays(2),
        ]);
        $this->attachItem($keepOrder, $product, $variant);
        Payment::factory()->paid()->create([
            'order_id' => $keepOrder->id,
            'user_id' => $regina->id,
            'amount' => 75000,
        ]);
        $keepTxn = PaymentTransaction::factory()->create([
            'order_id' => $keepOrder->id,
            'status' => PaymentTransactionStatus::Successful,
            'merchant_reference' => 'COTZ-PAY-20260811-000003',
            'amount' => 75000,
            'completed_at' => now()->subDays(2),
            'callback_received_at' => now()->subDays(2),
        ]);

        $abandoned = Order::factory()->create([
            'user_id' => $qa->id,
            'order_number' => 'COTZ-20260812-QA0001',
            'status' => OrderStatus::PendingPayment,
            'total' => 12000,
        ]);
        $this->attachItem($abandoned, $product, $variant);
        Payment::factory()->create([
            'order_id' => $abandoned->id,
            'user_id' => $qa->id,
            'amount' => 12000,
        ]);
        $abandonedTxn = PaymentTransaction::factory()->processing()->create([
            'order_id' => $abandoned->id,
            'merchant_reference' => 'COTZ-PAY-QA-ABANDONED',
            'amount' => 12000,
        ]);

        $fulfillment = Fulfillment::factory()->create(['order_id' => $abandoned->id]);
        Shipment::factory()->create([
            'order_id' => $abandoned->id,
            'fulfillment_id' => $fulfillment->id,
        ]);

        if ($this->hasOrderStatusHistoryTable()) {
            $row = [
                'id' => (string) Str::uuid(),
                'order_id' => $abandoned->id,
                'new_status' => OrderStatus::PendingPayment->value,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            DB::table('order_status_history')->insert($row);
        }

        return [
            'regina_user_id' => $regina->id,
            'qa_user_id' => $qa->id,
            'keep_order_id' => $keepOrder->id,
            'keep_order_number' => $keepOrder->order_number,
            'keep_txn_id' => $keepTxn->id,
            'abandoned_order_id' => $abandoned->id,
            'abandoned_txn_id' => $abandonedTxn->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
        ];
    }

    private function hasOrderStatusHistoryTable(): bool
    {
        return DB::getSchemaBuilder()->hasTable('order_status_history')
            && DB::getSchemaBuilder()->hasColumn('order_status_history', 'order_id');
    }

    private function attachItem(Order $order, Product $product, ProductVariant $variant): void
    {
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name_snapshot' => $product->name,
            'product_slug_snapshot' => $product->slug,
            'sku_snapshot' => $product->sku,
            'brand_name_snapshot' => null,
            'variant_name_snapshot' => $variant->name,
            'variant_sku_snapshot' => $variant->sku,
            'currency_snapshot' => 'TZS',
            'unit_price_snapshot' => $product->price,
            'shipping_mode_snapshot' => 'air',
            'shipping_price_snapshot' => 0,
            'product_name' => $product->name,
            'variant_name' => $variant->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'unit_price' => $product->price,
            'line_total' => $product->price,
            'total_price' => $product->price,
            'currency' => 'TZS',
            'shipping_method' => 'air',
            'shipping_price' => 0,
            'shipping_subtotal' => 0,
        ]);
    }
}

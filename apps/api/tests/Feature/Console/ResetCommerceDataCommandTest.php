<?php

namespace Tests\Feature\Console;

use App\Models\Admin;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CheckoutSession;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResetCommerceDataCommandTest extends TestCase
{
    public function test_command_is_registered(): void
    {
        Artisan::call('list', ['--raw' => true]);

        $this->assertStringContainsString('app:reset-commerce-data', Artisan::output());
    }

    public function test_command_deletes_transactional_commerce_data_and_preserves_catalog_and_users(): void
    {
        $user = User::factory()->create();
        Admin::factory()->create();
        $product = Product::factory()->create();

        $order = Order::factory()->create(['user_id' => $user->id]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
        ]);
        Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);
        PaymentTransaction::factory()->create(['order_id' => $order->id]);

        $fulfillment = Fulfillment::factory()->create(['order_id' => $order->id]);
        Shipment::factory()->create([
            'order_id' => $order->id,
            'fulfillment_id' => $fulfillment->id,
        ]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
        ]);
        CheckoutSession::factory()->create([
            'user_id' => $user->id,
            'cart_id' => $cart->id,
        ]);

        $productCountBefore = Product::query()->count();

        $this->artisan('app:reset-commerce-data --force')
            ->assertSuccessful()
            ->expectsOutputToContain('Deleted:')
            ->expectsOutputToContain('Orders: 1')
            ->expectsOutputToContain('Order Items: 1')
            ->expectsOutputToContain('Payments: 1')
            ->expectsOutputToContain('Payment Transactions: 1')
            ->expectsOutputToContain('Commerce transaction reset completed.');

        $this->assertSame(0, DB::table('orders')->count());
        $this->assertSame(0, DB::table('order_items')->count());
        $this->assertSame(0, DB::table('payments')->count());
        $this->assertSame(0, DB::table('payment_transactions')->count());
        $this->assertSame(0, DB::table('shipments')->count());
        $this->assertSame(0, DB::table('fulfillments')->count());
        $this->assertSame(0, DB::table('carts')->count());
        $this->assertSame(0, DB::table('cart_items')->count());
        $this->assertSame(0, DB::table('checkout_sessions')->count());

        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, Admin::query()->count());
        $this->assertSame($productCountBefore, Product::query()->count());
    }

    public function test_command_requires_confirmation_without_force_option(): void
    {
        Order::factory()->create();

        $this->artisan('app:reset-commerce-data')
            ->expectsConfirmation('Delete all transactional commerce data?', 'no')
            ->assertSuccessful()
            ->expectsOutputToContain('Cancelled.');

        $this->assertSame(1, DB::table('orders')->count());
    }
}

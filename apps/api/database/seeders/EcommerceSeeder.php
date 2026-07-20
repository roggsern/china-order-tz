<?php

namespace Database\Seeders;

use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\DeliveryAddress;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Review;
use App\Models\Role;
use App\Models\ShippingAddress;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\Seeder;

class EcommerceSeeder extends Seeder
{
    /**
     * Canonical local development customer credentials.
     * Plain password — User model casts `password` => `hashed` (bcrypt once).
     */
    public const DEFAULT_EMAIL = 'customer@chinaordertz.com';

    public const DEFAULT_PASSWORD = 'password';

    public function run(): void
    {
        $customerRole = Role::query()->where('slug', 'customer')->firstOrFail();

        $demoUser = User::query()->updateOrCreate(
            ['email' => self::DEFAULT_EMAIL],
            [
                'name' => 'Demo Customer',
                'phone' => '+255787654321',
                'password' => self::DEFAULT_PASSWORD,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $demoUser->roles()->syncWithoutDetaching([$customerRole->id]);

        Coupon::query()->updateOrCreate(
            ['code' => 'WELCOME10'],
            [
                'type' => CouponType::Percentage,
                'value' => 10,
                'min_order_amount' => 50000,
                'max_uses' => 1000,
                'used_count' => 0,
                'starts_at' => now()->subWeek(),
                'expires_at' => now()->addYear(),
                'is_active' => true,
            ]
        );

        // Factory demo graph is one-shot — re-running on boot must not duplicate rows.
        if (Order::query()->where('user_id', $demoUser->id)->exists()) {
            $this->command?->info('EcommerceSeeder skipped demo graph: already seeded for demo customer.');

            return;
        }

        $products = Product::query()
            ->where('is_demo', true)
            ->inRandomOrder()
            ->limit(5)
            ->get();

        if ($products->isEmpty()) {
            $products = Product::query()->inRandomOrder()->limit(5)->get();
        }

        if ($products->isEmpty()) {
            $this->command?->warn('EcommerceSeeder skipped demo graph: no products available.');

            return;
        }

        // Extra demo customers only when catalog exists; skip if already populated.
        if (User::query()->where('email', '!=', self::DEFAULT_EMAIL)->count() < 10) {
            User::factory(10)->create()->each(function (User $user) use ($customerRole) {
                $user->roles()->syncWithoutDetaching([$customerRole->id]);
            });
        }

        ShippingAddress::factory()->default()->create(['user_id' => $demoUser->id]);
        ShippingAddress::factory(2)->create(['user_id' => $demoUser->id]);

        DeliveryAddress::factory()->create([
            'user_id' => $demoUser->id,
            'recipient_name' => $demoUser->name,
            'phone' => $demoUser->phone,
        ]);

        $wishlist = Wishlist::factory()->create([
            'user_id' => $demoUser->id,
            'name' => 'Default',
        ]);

        foreach ($products->take(3) as $product) {
            $wishlist->items()->create([
                'product_id' => $product->id,
            ]);
        }

        $cart = Cart::factory()->create(['user_id' => $demoUser->id]);

        foreach ($products->take(2) as $product) {
            CartItem::factory()->create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'unit_price' => $product->price,
            ]);
        }

        $welcomeCoupon = Coupon::query()->where('code', 'WELCOME10')->firstOrFail();

        Coupon::factory(5)->create();

        $order = Order::factory()->create([
            'user_id' => $demoUser->id,
            'coupon_id' => $welcomeCoupon->id,
            'status' => OrderStatus::Confirmed,
            'is_demo' => true,
        ]);

        foreach ($products->take(2) as $product) {
            OrderItem::factory()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'quantity' => 1,
                'unit_price' => $product->price,
                'total_price' => $product->price,
            ]);
        }

        ShippingAddress::factory()->create([
            'user_id' => $demoUser->id,
            'order_id' => $order->id,
        ]);

        Payment::factory()->completed()->create([
            'order_id' => $order->id,
            'user_id' => $demoUser->id,
            'method' => PaymentMethod::Mpesa,
            'status' => PaymentStatus::Paid,
            'amount' => $order->total,
        ]);

        Review::factory()->verified($order)->create([
            'user_id' => $demoUser->id,
            'product_id' => $products->first()->id,
            'rating' => 5,
            'title' => 'Excellent product',
            'comment' => 'Fast delivery and great quality. Highly recommended!',
        ]);

        Notification::factory(5)->unread()->create(['user_id' => $demoUser->id]);
    }
}

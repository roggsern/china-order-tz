<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetDemoCustomers extends Command
{
    protected $signature = 'customers:reset-demo';

    protected $description = 'Remove demo customer ecosystem data for clean UAT testing';

    public function handle(): int
    {
        if (! $this->confirm('This will delete all customer users, orders, carts and related data. Continue?')) {
            $this->info('Cancelled.');
            return self::SUCCESS;
        }

        DB::transaction(function () {

            // Customer generated data
            DB::table('cart_items')->delete();
            DB::table('carts')->delete();

            DB::table('wishlist_items')->delete();
            DB::table('wishlists')->delete();

            DB::table('reviews')->delete();

            DB::table('user_addresses')->delete();
            DB::table('delivery_addresses')->delete();
            DB::table('shipping_addresses')->delete();

            DB::table('customer_profiles')->delete();
            DB::table('user_profiles')->delete();

            DB::table('notifications')->delete();
            DB::table('notification_preferences')->delete();

            DB::table('personal_access_tokens')->delete();
            DB::table('sessions')->delete();

            // Commerce customer history
            DB::table('refunds')->delete();
            DB::table('payments')->delete();

            DB::table('return_requests')->delete();

            DB::table('order_status_history')->delete();
            DB::table('orders')->delete();

            // Permissions relation
            DB::table('role_user')->delete();

            // Users last
            DB::table('users')->delete();
        });

        $this->info('Demo customer ecosystem reset completed.');

        return self::SUCCESS;
    }
}
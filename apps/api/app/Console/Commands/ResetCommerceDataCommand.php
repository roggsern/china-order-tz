<?php

namespace App\Console\Commands;

use App\Services\Commerce\ResetCommerceDataService;
use Illuminate\Console\Command;

class ResetCommerceDataCommand extends Command
{
    protected $signature = 'app:reset-commerce-data
                            {--force : Skip the confirmation prompt (testing/automation only)}';

    protected $description = 'Delete transactional commerce records for local development cleanup';

    public function handle(ResetCommerceDataService $service): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This command is restricted to local and testing environments.');

            return self::FAILURE;
        }

        $this->warn('This will permanently delete transactional commerce data:');
        $this->line('  orders, order items, payments, shipments, fulfillments, carts, checkout sessions, returns/refunds, and related records.');
        $this->newLine();
        $this->line('Catalog, users, admins, roles, permissions, and settings will be preserved.');
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('Delete all transactional commerce data?', false)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $result = $service->handle();
        $deleted = $result['deleted'];

        $this->newLine();
        $this->info('Deleted:');
        $this->line('Orders: '.$deleted['orders']);
        $this->line('Order Items: '.$deleted['order_items']);
        $this->line('Payments: '.$deleted['payments']);
        $this->line('Payment Transactions: '.$deleted['payment_transactions']);
        $this->line('Shipments: '.$deleted['shipments']);
        $this->line('Fulfillment records: '.$deleted['fulfillment_records']);
        $this->line('Cart records: '.$deleted['cart_records']);

        if ($deleted['returns_and_refunds'] > 0) {
            $this->line('Returns / refunds: '.$deleted['returns_and_refunds']);
        }

        if ($deleted['china_order_requests'] > 0) {
            $this->line('China order records: '.$deleted['china_order_requests']);
        }

        $remaining = $result['remaining'];
        $this->newLine();

        if ($remaining === []) {
            $this->info('No remaining transactional rows detected in adjacent tables (purchase orders, POS sessions, reviews, loyalty ledger).');
        } else {
            $this->warn('Remaining transactional-adjacent rows (not deleted by this command):');
            foreach ($remaining as $table => $count) {
                $this->line(sprintf('%s: %d', $table, $count));
            }
        }

        $this->newLine();
        $this->info('Commerce transaction reset completed.');

        return self::SUCCESS;
    }
}

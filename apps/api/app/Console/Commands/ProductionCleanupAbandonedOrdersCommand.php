<?php

namespace App\Console\Commands;

use App\Services\Production\AbandonedOrderCleanupManifest;
use App\Services\Production\AbandonedOrderCleanupService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class ProductionCleanupAbandonedOrdersCommand extends Command
{
    protected $signature = 'production:cleanup-abandoned-orders
                            {--dry-run : Report only; make no writes (default without --force)}
                            {--force : Allow destructive execution (still requires --confirm)}
                            {--confirm= : Must equal DELETE_ABANDONED_ORDERS_KEEP_PAID for destructive runs}
                            {--keep-order= : Order number that must remain (required; e.g. COTZ-20260811-000005)}';

    protected $description = 'Selectively delete abandoned/QA orders while permanently keeping a named paid order and all customer accounts';

    public function handle(AbandonedOrderCleanupService $service): int
    {
        $force = (bool) $this->option('force');
        $dryRunFlag = (bool) $this->option('dry-run');
        $confirm = (string) ($this->option('confirm') ?? '');
        $keepOrder = trim((string) ($this->option('keep-order') ?? ''));
        $isDryRun = ! $force || $dryRunFlag;

        if ($keepOrder === '') {
            $this->error('Missing required --keep-order=<order_number>.');
            $this->line('Example: --keep-order='.AbandonedOrderCleanupManifest::DOCUMENTED_PROTECTED_ORDER_NUMBER);

            return self::FAILURE;
        }

        try {
            $preview = $service->preview($keepOrder);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->printSafetyBanner($preview);

        $this->newLine();
        $this->info('=== PROTECTED ORDER ===');
        $p = $preview['protected_order'];
        $this->line('Order number: '.$p['order_number']);
        $this->line('Order id: '.$p['id']);
        $this->line('Customer: '.($p['customer_email'] ?? '(none)'));
        $this->line('Status: '.$p['status']);
        $this->line('Total: '.$p['total']);
        $this->line('Payment txn: '.$p['payment_transaction_id']);
        $this->line('Payment status: '.$p['payment_status']);
        $this->line('Merchant reference: '.($p['merchant_reference'] ?? 'n/a'));
        $this->line('Completed at: '.($p['completed_at'] ?? 'n/a'));

        $this->newLine();
        $this->info('=== DELETE CANDIDATES ===');
        if ($preview['delete_candidates'] === []) {
            $this->line('(none)');
        } else {
            foreach ($preview['delete_candidates'] as $order) {
                $this->line(sprintf(
                    '%s  %s  %s  status=%s  total=%s',
                    $order['id'],
                    $order['order_number'],
                    $order['customer_email'] ?? '(no email)',
                    $order['status'],
                    $order['total'],
                ));
            }
        }

        $this->newLine();
        $this->info('=== PAYMENT TRANSACTIONS TO DELETE ===');
        if ($preview['payment_transactions_to_delete'] === []) {
            $this->line('(none)');
        } else {
            foreach ($preview['payment_transactions_to_delete'] as $txn) {
                $this->line(sprintf(
                    '%s  ref=%s  status=%s  callback=%s  completed=%s',
                    $txn['id'],
                    $txn['merchant_reference'] ?? 'n/a',
                    $txn['status'],
                    $txn['callback_received'] ? 'yes' : 'no',
                    $txn['completed'] ? 'yes' : 'no',
                ));
            }
        }

        $this->newLine();
        $this->info('=== DEPENDENCY COUNTS (order-bound, non-zero) ===');
        foreach ($preview['dependency_counts'] as $table => $count) {
            if ((int) $count > 0) {
                $this->line(sprintf('%-42s %d', $table, $count));
            }
        }

        $this->newLine();
        $this->info('=== PRESERVE COUNTS ===');
        $this->printKeyCounts($preview['preserve_counts']);

        $this->newLine();
        $this->comment($preview['inventory_note']);
        $this->comment('This command never calls NMB / payment gateways.');
        $this->comment('Customer accounts, carts, wishlists, and catalog are preserved.');

        if ($isDryRun) {
            $this->newLine();
            $this->info('DRY RUN — NO WRITES PERFORMED');
            if ($force && $dryRunFlag) {
                $this->comment('--force was ignored because --dry-run was also set.');
            } elseif (! $force) {
                $this->comment(
                    'Re-run with --force --confirm='
                    .AbandonedOrderCleanupManifest::CONFIRMATION_PHRASE
                    .' --keep-order='.$keepOrder
                    .' to execute.',
                );
            }

            return self::SUCCESS;
        }

        if ($confirm !== AbandonedOrderCleanupManifest::CONFIRMATION_PHRASE) {
            $this->error('Destructive execution blocked: invalid or missing --confirm phrase.');
            $this->line('Required: --confirm='.AbandonedOrderCleanupManifest::CONFIRMATION_PHRASE);

            return self::FAILURE;
        }

        $this->newLine();
        $this->warn('DESTRUCTIVE EXECUTION starting (single DB transaction)…');

        try {
            $result = $service->execute($keepOrder);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('=== DELETED (non-zero tables) ===');
        foreach ($result['deleted_tables'] as $table => $count) {
            if ((int) $count > 0) {
                $this->line(sprintf('%-42s %d', $table, $count));
            }
        }

        $this->newLine();
        $this->info('=== PROTECTED ORDER (after) ===');
        $after = $result['protected_order'];
        $this->line(sprintf(
            '%s  status=%s  payment=%s',
            $after['order_number'],
            $after['status'],
            $after['payment_status'],
        ));

        $this->newLine();
        $this->info('=== PRESERVE CHECKS (after) ===');
        $this->printKeyCounts($result['preserve_counts']);

        if ($result['catalog_drift'] !== []) {
            $this->error('Unexpected preserve drift:');
            foreach ($result['catalog_drift'] as $key => $pair) {
                $this->line(sprintf('  %s: before=%d after=%d', $key, $pair['before'], $pair['after']));
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Abandoned-order cleanup completed. Protected paid order and customers preserved.');
        $this->comment('Idempotent: re-run --dry-run to confirm zero delete candidates.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $preview
     */
    private function printSafetyBanner(array $preview): void
    {
        $this->newLine();
        $this->warn('=== TARGET DATABASE (verify before destroy) ===');
        $this->line('Environment: '.$preview['environment']);
        $this->line('Database host: '.$preview['database_host']);
        $this->line('Database name: '.$preview['database_name']);
        $this->line('Keep order: '.$preview['keep_order_number']);
        $this->comment('Passwords/secrets are never printed.');
        $this->comment('Does NOT delete users, customer profiles, catalog, or inventory balance rows.');
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function printKeyCounts(array $counts): void
    {
        foreach ($counts as $key => $count) {
            $this->line(sprintf('%-42s %d', $key, $count));
        }
    }
}

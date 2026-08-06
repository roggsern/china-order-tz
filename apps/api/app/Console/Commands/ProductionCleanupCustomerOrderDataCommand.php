<?php

namespace App\Console\Commands;

use App\Services\Production\CustomerOrderDataCleanupManifest;
use App\Services\Production\CustomerOrderDataCleanupService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class ProductionCleanupCustomerOrderDataCommand extends Command
{
    protected $signature = 'production:cleanup-customer-order-data
                            {--dry-run : Report counts only; make no writes (default without --force)}
                            {--force : Allow destructive execution (still requires --confirm)}
                            {--confirm= : Must equal DELETE_PRELAUNCH_CUSTOMERS_AND_ORDERS for destructive runs}';

    protected $description = 'Remove pre-launch customers and orders while preserving the complete catalog';

    public function handle(CustomerOrderDataCleanupService $service): int
    {
        $force = (bool) $this->option('force');
        $dryRunFlag = (bool) $this->option('dry-run');
        $confirm = (string) ($this->option('confirm') ?? '');
        $isDryRun = ! $force || $dryRunFlag;

        try {
            $preview = $service->preview();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->printSafetyBanner($preview);

        $this->newLine();
        $this->info('=== CUSTOMERS TO DELETE ===');
        if ($preview['customers'] === []) {
            $this->line('(none)');
        } else {
            foreach ($preview['customers'] as $customer) {
                $this->line(sprintf(
                    '%s  %s  %s',
                    $customer['id'],
                    $customer['email'] ?? '(no email)',
                    $customer['created_at'] ?? '',
                ));
            }
        }

        $this->newLine();
        $this->info('=== ORDERS TO DELETE ===');
        if ($preview['orders'] === []) {
            $this->line('(none)');
        } else {
            foreach ($preview['orders'] as $order) {
                $this->line(sprintf(
                    '%s  %s  user=%s  status=%s',
                    $order['id'],
                    $order['order_number'] ?? '(no number)',
                    $order['user_id'] ?? 'null',
                    $order['status'] ?? 'n/a',
                ));
            }
        }

        $this->newLine();
        $this->info('=== PROVENANCE (read-only) ===');
        foreach ($preview['provenance'] as $row) {
            $focus = ($row['focus_match'] ?? false) ? ' [FOCUS]' : '';
            $this->line(sprintf(
                '%s%s  proven_path=%s',
                $row['email'] ?? $row['user_id'],
                $focus,
                $row['proven_path'],
            ));
            foreach ($row['conclusions'] as $conclusion) {
                $this->line('  conclusion: '.$conclusion);
            }
            foreach (array_slice($row['evidence'], 0, 8) as $evidence) {
                $this->line(sprintf('  [%s] %s', $evidence['source'], $evidence['detail']));
            }
            if (count($row['evidence']) > 8) {
                $this->line('  … '.((int) count($row['evidence']) - 8).' more evidence lines');
            }
            $this->newLine();
        }

        $this->info('=== DELETE DOMAIN COUNTS ===');
        $this->printKeyCounts($preview['domain_counts']);

        $this->newLine();
        $this->info('=== PER-TABLE DEPENDENCY COUNTS (non-zero) ===');
        foreach ($preview['table_counts'] as $table => $count) {
            if ((int) $count > 0) {
                $this->line(sprintf('%-42s %d', $table, $count));
            }
        }

        $this->newLine();
        $this->info('=== CATALOG / FOUNDATION THAT WILL REMAIN ===');
        $this->printKeyCounts($preview['preserve_counts']);

        $this->newLine();
        $this->info('=== SAFETY ===');
        $this->line('Customer identity proven (users ≠ admins): '
            .(($preview['customer_identity_proven'] ?? false) ? 'yes' : 'no'));
        $this->line('Catalog excluded from deletion manifest: '
            .(($preview['catalog_excluded_proven'] ?? false) ? 'yes' : 'no'));
        $this->line('Customer users to delete: '.$preview['customer_users']);
        $this->line('Admins preserved: '.$preview['admins']);

        if ($isDryRun) {
            $this->newLine();
            $this->info('DRY RUN — no database writes performed.');
            if ($force && $dryRunFlag) {
                $this->comment('--force was ignored because --dry-run was also set.');
            } elseif (! $force) {
                $this->comment(
                    'Re-run with --force --confirm='
                    .CustomerOrderDataCleanupManifest::CONFIRMATION_PHRASE
                    .' to execute.',
                );
            }

            return self::SUCCESS;
        }

        if ($confirm !== CustomerOrderDataCleanupManifest::CONFIRMATION_PHRASE) {
            $this->error('Destructive execution blocked: invalid or missing --confirm phrase.');
            $this->line('Required: --confirm='.CustomerOrderDataCleanupManifest::CONFIRMATION_PHRASE);

            return self::FAILURE;
        }

        $this->newLine();
        $this->warn('DESTRUCTIVE EXECUTION starting (catalog must remain unchanged)…');

        try {
            $result = $service->execute();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('=== DELETED (by domain) ===');
        $this->printKeyCounts($result['domain_deleted']);

        $this->newLine();
        $this->info('=== PRESERVE CHECKS (after) ===');
        $this->printKeyCounts($result['preserve_counts']);

        if ($result['catalog_drift'] !== []) {
            $this->error('Catalog drift detected (cleanup should never change these):');
            foreach ($result['catalog_drift'] as $key => $pair) {
                $this->line(sprintf('  %s: before=%d after=%d', $key, $pair['before'], $pair['after']));
            }

            return self::FAILURE;
        }

        if (! $result['post_zero_ok']) {
            $this->error('Post-cleanup zero-check failed:');
            foreach ($result['post_zero_failures'] as $table => $count) {
                $this->line(sprintf('  %s: %d remaining', $table, $count));
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Customer/order cleanup completed. Catalog preserved.');
        $this->comment('Idempotent: re-run --dry-run to confirm zero customers/orders.');

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
        $this->line('Customer users: '.$preview['customer_users']);
        $this->line('Admins: '.$preview['admins']);
        $this->comment('Passwords/secrets are never printed.');
        $this->comment('This command does NOT delete products, variants, inventory, or product media.');
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

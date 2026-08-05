<?php

namespace App\Console\Commands;

use App\Services\Production\CommerceDataCleanupManifest;
use App\Services\Production\CommerceDataCleanupService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class ProductionCleanupCommerceDataCommand extends Command
{
    protected $signature = 'production:cleanup-commerce-data
                            {--dry-run : Report counts only; make no writes (default without --force)}
                            {--force : Allow destructive execution (still requires --confirm)}
                            {--confirm= : Must equal DELETE_TEST_COMMERCE_DATA for destructive runs}';

    protected $description = 'Remove test/demo commerce data while preserving platform foundation (production-safe)';

    public function handle(CommerceDataCleanupService $service): int
    {
        $force = (bool) $this->option('force');
        $dryRunFlag = (bool) $this->option('dry-run');
        $confirm = (string) ($this->option('confirm') ?? '');

        // Default without --force: never write.
        $isDryRun = ! $force || $dryRunFlag;

        try {
            $preview = $service->preview();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->printSafetyBanner($preview);

        if ($preview['admins'] < 1) {
            $this->warn('Warning: zero admins found. Proceed only if intentional.');
        }

        $this->newLine();
        $this->info('=== PRESERVE CHECKS (must remain) ===');
        $this->printKeyCounts($preview['preserve_counts']);

        $this->newLine();
        $this->info('=== DELETE DOMAIN COUNTS ===');
        $this->printKeyCounts($preview['domain_counts']);

        $this->newLine();
        $this->info('=== CUSTOMER SAFETY ===');
        $this->line('Customer identity proven (users ≠ admins): yes');
        $this->line('Customer users to delete: '.$preview['customer_users']);
        $this->line('Admins preserved: '.$preview['admins']);

        $this->newLine();
        $this->info('=== MEDIA CLEANUP PLAN ===');
        $this->line('Media DB rows scanned: '.$preview['media']['rows']);
        $this->line('Physical files expected for removal: '.$preview['media']['files_expected']);
        if ($preview['media']['files_expected'] > 0) {
            $sample = array_slice($preview['media']['paths'], 0, 10);
            foreach ($sample as $path) {
                $this->line('  - '.$path);
            }
            if ($preview['media']['files_expected'] > 10) {
                $this->line('  … and '.($preview['media']['files_expected'] - 10).' more');
            }
        }

        if ($isDryRun) {
            $this->newLine();
            $this->info('DRY RUN — no database or media writes performed.');
            if ($force && $dryRunFlag) {
                $this->comment('--force was ignored because --dry-run was also set.');
            } elseif (! $force) {
                $this->comment('Re-run with --force --confirm='.CommerceDataCleanupManifest::CONFIRMATION_PHRASE.' to execute.');
            }

            return self::SUCCESS;
        }

        // Destructive path
        if ($confirm !== CommerceDataCleanupManifest::CONFIRMATION_PHRASE) {
            $this->error('Destructive execution blocked: invalid or missing --confirm phrase.');
            $this->line('Required: --confirm='.CommerceDataCleanupManifest::CONFIRMATION_PHRASE);

            return self::FAILURE;
        }

        $this->newLine();
        $this->warn('DESTRUCTIVE EXECUTION starting…');

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
        $this->info('=== MEDIA FILES ===');
        $this->line('Deleted files: '.$result['media']['deleted_files']);
        $this->line('Missing files (ignored): '.$result['media']['missing_files']);
        $this->line('Skipped unsafe paths: '.$result['media']['skipped_unsafe']);

        $this->newLine();
        $this->info('=== PRESERVE CHECKS (after) ===');
        $this->printKeyCounts($result['preserve_counts']);

        if (! $result['post_zero_ok']) {
            $this->error('Post-cleanup zero-check failed:');
            foreach ($result['post_zero_failures'] as $table => $count) {
                $this->line(sprintf('  %s: %d remaining', $table, $count));
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Commerce cleanup completed. Foundation data preserved.');
        $this->comment('Idempotent: re-run --dry-run to confirm zeros.');

        return self::SUCCESS;
    }

    /**
     * @param  array{
     *     environment: string,
     *     database_host: string,
     *     database_name: string,
     *     customer_users: int,
     *     admins: int
     * }  $preview
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

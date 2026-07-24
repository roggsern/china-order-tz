<?php

namespace App\Console\Commands;

use App\Services\Catalog\TzLocalStoreOwnershipAuditor;
use App\Services\Catalog\TzLocalStoreOwnershipBackfill;
use Illuminate\Console\Command;

class CatalogAuditTzStoreOwnershipCommand extends Command
{
    protected $signature = 'catalog:audit-tz-store-ownership
                            {--json : Output machine-readable JSON report}';

    protected $description = 'Audit TZ_LOCAL products missing store_id and summarize lifecycle breakdown.';

    public function handle(TzLocalStoreOwnershipAuditor $auditor): int
    {
        $report = $auditor->audit();

        if ((bool) $this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('TZ_LOCAL store ownership audit');
        $this->line('  TZ channel id: '.($report['tz_channel_id'] ?? 'not seeded'));
        $this->line('  Total affected products: '.$report['total_affected']);
        $this->line('  Active: '.$report['active']);
        $this->line('  Out of stock: '.$report['out_of_stock']);
        $this->line('  Draft: '.$report['draft']);
        $this->line('  Archived: '.$report['archived']);
        if ($report['other_lifecycle'] > 0) {
            $this->line('  Other lifecycle: '.$report['other_lifecycle']);
        }
        $this->line('  Auto-assignable from category store: '.$report['auto_assignable_from_category']);
        $this->line('  Requires manual assignment: '.$report['requires_manual_assignment']);

        if ($report['total_affected'] === 0) {
            $this->info('No TZ_LOCAL products missing store_id.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['Product', 'Slug', 'Lifecycle', 'Category store', 'Manual required'],
            collect($report['products'])->map(fn (array $row) => [
                $row['name'],
                $row['slug'],
                $row['lifecycle_status'],
                $row['category_store_id'] ?? '—',
                $row['manual_assignment_required'] ? 'yes' : 'no',
            ])->all(),
        );

        $this->newLine();
        $this->comment('Next steps:');
        $this->line('  Dry-run backfill: php artisan catalog:backfill-tz-store-ownership');
        $this->line('  Explicit store:    php artisan catalog:backfill-tz-store-ownership --store-id=<uuid> --include-listed --include-without-category-store --execute');

        return self::SUCCESS;
    }
}

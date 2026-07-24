<?php

namespace App\Console\Commands;

use App\Services\Catalog\CommerceChannelAuditor;
use Illuminate\Console\Command;

class CatalogAuditCommerceChannelCommand extends Command
{
    protected $signature = 'catalog:audit-commerce-channel
                            {--json : Output machine-readable JSON report}';

    protected $description = 'Audit legacy China-import products missing commerce_channel_id.';

    public function handle(CommerceChannelAuditor $auditor): int
    {
        $report = $auditor->audit();

        if ((bool) $this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('Commerce channel audit');
        $this->line('  CHINA_IMPORT channel id: '.($report['china_channel_id'] ?? 'not seeded'));
        $this->line('  Total affected products: '.$report['total_affected']);
        $this->line('  Active: '.$report['active']);
        $this->line('  Out of stock: '.$report['out_of_stock']);
        $this->line('  Draft: '.$report['draft']);
        $this->line('  Archived: '.$report['archived']);
        if ($report['other_lifecycle'] > 0) {
            $this->line('  Other lifecycle: '.$report['other_lifecycle']);
        }

        if ($report['total_affected'] === 0) {
            $this->info('No legacy imported_from_china products missing commerce_channel_id.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['Product', 'Slug', 'Lifecycle', 'fulfillment_source'],
            collect($report['products'])->map(fn (array $row) => [
                $row['name'],
                $row['slug'],
                $row['lifecycle_status'],
                $row['fulfillment_source'] ?? '—',
            ])->all(),
        );

        $this->newLine();
        $this->comment('Next step: php artisan catalog:backfill-commerce-channel --execute');

        return self::SUCCESS;
    }
}

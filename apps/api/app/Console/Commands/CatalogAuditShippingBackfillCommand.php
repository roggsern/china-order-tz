<?php

namespace App\Console\Commands;

use App\Services\Catalog\ShippingOptionsBackfillAuditor;
use Illuminate\Console\Command;

class CatalogAuditShippingBackfillCommand extends Command
{
    protected $signature = 'catalog:audit-shipping-backfill
                            {--json : Output machine-readable JSON report}';

    protected $description = 'Audit CHINA_IMPORT products eligible for legacy shipping option backfill.';

    public function handle(ShippingOptionsBackfillAuditor $auditor): int
    {
        $report = $auditor->audit();

        if ((bool) $this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('Shipping options backfill audit');
        $this->line('  CHINA_IMPORT channel id: '.($report['china_channel_id'] ?? 'not seeded'));
        $this->line('  Total eligible products: '.$report['total_eligible']);
        $this->line('  Active: '.$report['active']);
        $this->line('  Out of stock: '.$report['out_of_stock']);
        $this->line('  Draft: '.$report['draft']);
        $this->line('  Archived: '.$report['archived']);
        if ($report['other_lifecycle'] > 0) {
            $this->line('  Other lifecycle: '.$report['other_lifecycle']);
        }

        if ($report['total_eligible'] === 0) {
            $this->info('No CHINA_IMPORT products eligible for shipping option backfill.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['Product', 'Lifecycle', 'Air', 'Sea', 'Options', 'Priced', 'Planned modes'],
            collect($report['products'])->map(fn (array $row) => [
                $row['name'],
                $row['lifecycle_status'],
                $row['air_shipping_price'] ?? '—',
                $row['sea_shipping_price'] ?? '—',
                $row['shipping_options_count'],
                $row['available_priced_options_count'],
                implode(', ', $row['planned_modes']),
            ])->all(),
        );

        $this->newLine();
        $this->comment('Next step: php artisan catalog:backfill-shipping-options --execute');

        return self::SUCCESS;
    }
}

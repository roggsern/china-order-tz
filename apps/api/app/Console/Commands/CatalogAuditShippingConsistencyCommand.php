<?php

namespace App\Console\Commands;

use App\Services\Catalog\ShippingConsistencyAuditor;
use Illuminate\Console\Command;

class CatalogAuditShippingConsistencyCommand extends Command
{
    protected $signature = 'catalog:audit-shipping-consistency
                            {--json : Output machine-readable JSON report}';

    protected $description = 'Audit product shipping data consistency against commerce channel rules.';

    public function handle(ShippingConsistencyAuditor $auditor): int
    {
        $report = $auditor->audit();

        if ((bool) $this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('Shipping consistency audit');
        $this->line('  CHINA_IMPORT channel id: '.($report['china_channel_id'] ?? 'not seeded'));
        $this->line('  TZ_LOCAL channel id: '.($report['tz_channel_id'] ?? 'not seeded'));
        $this->newLine();

        $this->renderChinaMissingShipping($report['china_import_missing_shipping']);
        $this->newLine();
        $this->renderTzInvalidFreight($report['tz_local_invalid_freight']);
        $this->newLine();
        $this->renderLegacyMissingChannel($report['legacy_missing_commerce_channel']);

        return self::SUCCESS;
    }

    /**
     * @param  array{total: int, products: list<array<string, mixed>>}  $section
     */
    private function renderChinaMissingShipping(array $section): void
    {
        $this->info('CHINA_IMPORT products missing publishable shipping');
        $this->line('  Total: '.$section['total']);

        if ($section['total'] === 0) {
            $this->comment('  No CHINA_IMPORT products missing shipping options.');

            return;
        }

        $this->table(
            ['Product', 'Lifecycle', 'Air', 'Sea', 'Options', 'Priced available'],
            collect($section['products'])->map(fn (array $row) => [
                $row['name'],
                $row['lifecycle_status'],
                $row['air_shipping_price'] ?? '—',
                $row['sea_shipping_price'] ?? '—',
                $row['shipping_options_count'],
                $row['available_priced_options_count'],
            ])->all(),
        );
    }

    /**
     * @param  array{total: int, products: list<array<string, mixed>>}  $section
     */
    private function renderTzInvalidFreight(array $section): void
    {
        $this->info('TZ_LOCAL products with invalid China freight data');
        $this->line('  Total: '.$section['total']);

        if ($section['total'] === 0) {
            $this->comment('  No TZ_LOCAL products with invalid freight data.');

            return;
        }

        $this->table(
            ['Product', 'Lifecycle', 'Air', 'Sea', 'Options', 'Issues'],
            collect($section['products'])->map(fn (array $row) => [
                $row['name'],
                $row['lifecycle_status'],
                $row['air_shipping_price'] ?? '—',
                $row['sea_shipping_price'] ?? '—',
                $row['shipping_options_count'],
                implode(', ', $row['issues']),
            ])->all(),
        );
    }

    /**
     * @param  array{
     *     total: int,
     *     fulfillment_sources: list<string>,
     *     products: list<array<string, mixed>>
     * }  $section
     */
    private function renderLegacyMissingChannel(array $section): void
    {
        $this->info('Legacy China-import rows missing commerce_channel_id');
        $this->line('  Total needing migration: '.$section['total']);

        if ($section['fulfillment_sources'] !== []) {
            $this->line('  Fulfillment sources seen: '.implode(', ', $section['fulfillment_sources']));
        }

        if ($section['total'] === 0) {
            $this->comment('  No legacy rows missing commerce_channel_id.');

            return;
        }

        $this->table(
            ['Product', 'Lifecycle', 'fulfillment_source'],
            collect($section['products'])->map(fn (array $row) => [
                $row['name'],
                $row['lifecycle_status'],
                $row['fulfillment_source'] ?? '—',
            ])->all(),
        );
    }
}

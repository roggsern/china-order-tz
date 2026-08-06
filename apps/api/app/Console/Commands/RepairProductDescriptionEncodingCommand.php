<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\Catalog\ProductDescriptionEncodingRepair;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Repair product description/short_description mojibake from the pre-charset HtmlSanitizer bug.
 *
 * Default is dry-run. Write mode requires --force and --confirm.
 * Deploy the HtmlSanitizer UTF-8 fix BEFORE running write mode.
 */
class RepairProductDescriptionEncodingCommand extends Command
{
    protected $signature = 'products:repair-description-encoding
                            {--product= : Optional product UUID}
                            {--force : Required to apply updates}
                            {--confirm= : Must equal REPAIR_PRODUCT_DESCRIPTION_ENCODING}
                            {--dry-run : Report only (default when --force is omitted)}';

    protected $description = 'Detect and repair UTF-8 mojibake in product description fields (dry-run by default)';

    public function handle(ProductDescriptionEncodingRepair $repair): int
    {
        $productId = $this->option('product');
        $force = (bool) $this->option('force');
        $dryRunFlag = (bool) $this->option('dry-run');
        $confirm = (string) ($this->option('confirm') ?? '');

        $dryRun = $dryRunFlag || ! $force;

        if ($force && $dryRunFlag) {
            $this->comment('--force was ignored because --dry-run was also set.');
            $dryRun = true;
        }

        if (! $dryRun) {
            if ($confirm !== ProductDescriptionEncodingRepair::CONFIRMATION_PHRASE) {
                $this->error('Refusing write mode without matching confirmation.');
                $this->line('Required: --force --confirm='.ProductDescriptionEncodingRepair::CONFIRMATION_PHRASE);

                return self::FAILURE;
            }
        }

        $query = Product::query()->orderBy('id');
        if (filled($productId)) {
            $query->whereKey($productId);
        }

        $scanned = 0;
        $candidates = 0;
        $skipped = 0;
        $updated = 0;

        $this->info($dryRun ? 'Dry-run: no rows will be updated.' : 'Write mode: applying verified repairs.');
        $this->newLine();

        $query->chunkById(100, function ($products) use (
            $repair,
            $dryRun,
            &$scanned,
            &$candidates,
            &$skipped,
            &$updated,
        ): void {
            foreach ($products as $product) {
                $scanned++;
                $fields = [];

                foreach (['description', 'short_description'] as $field) {
                    $evaluation = $repair->evaluate($product->{$field});
                    if ($evaluation === null) {
                        continue;
                    }

                    if (! $evaluation['candidate']) {
                        if ($evaluation['reason'] === 'ambiguous_or_unrecoverable') {
                            $skipped++;
                            $this->warn(sprintf(
                                'SKIP  %s  %s  field=%s  reason=%s  preview=%s',
                                $product->id,
                                $product->name,
                                $field,
                                $evaluation['reason'],
                                $repair->preview((string) $product->{$field}),
                            ));
                        }

                        continue;
                    }

                    $fields[$field] = $evaluation;
                }

                if ($fields === []) {
                    continue;
                }

                $candidates++;

                foreach ($fields as $field => $evaluation) {
                    $before = (string) $product->{$field};
                    $after = (string) $evaluation['repaired'];

                    $this->line(sprintf(
                        '%s  %s  %s  field=%s  depth=%d',
                        $dryRun ? 'WOULD' : 'REPAIR',
                        $product->id,
                        $product->name,
                        $field,
                        (int) $evaluation['depth'],
                    ));
                    $this->line('  before: '.$repair->preview($before));
                    $this->line('  after:  '.$repair->preview($after));
                }

                if ($dryRun) {
                    continue;
                }

                DB::transaction(function () use ($product, $fields, $repair, &$updated): void {
                    /** @var Product $locked */
                    $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
                    $changes = [];
                    $old = [];
                    $new = [];

                    foreach ($fields as $field => $evaluation) {
                        // Re-evaluate under lock; skip if no longer a clean candidate.
                        $fresh = $repair->evaluate($locked->{$field});
                        if ($fresh === null || ! $fresh['candidate'] || $fresh['repaired'] === null) {
                            continue;
                        }

                        $old[$field] = $locked->{$field};
                        $locked->{$field} = $fresh['repaired'];
                        $new[$field] = $fresh['repaired'];
                        $changes[$field] = [
                            'depth' => $fresh['depth'],
                            'markers_before' => $repair->markerCount((string) $old[$field]),
                            'markers_after' => $repair->markerCount((string) $new[$field]),
                        ];
                    }

                    if ($changes === []) {
                        return;
                    }

                    $locked->save();
                    $updated++;

                    Log::info('product_description_encoding_repaired', [
                        'product_id' => $locked->id,
                        'product_name' => $locked->name,
                        'product_slug' => $locked->slug,
                        'fields' => array_keys($changes),
                        'changes' => $changes,
                        'old_preview' => array_map(
                            fn ($v) => $repair->preview((string) $v),
                            $old,
                        ),
                        'new_preview' => array_map(
                            fn ($v) => $repair->preview((string) $v),
                            $new,
                        ),
                    ]);
                });
            }
        });

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Scanned products', (string) $scanned],
                ['Repairable products', (string) $candidates],
                ['Ambiguous skipped fields', (string) $skipped],
                ['Products updated', $dryRun ? '0 (dry-run)' : (string) $updated],
            ],
        );

        if ($dryRun) {
            $this->comment(
                'Re-run with --force --confirm='
                .ProductDescriptionEncodingRepair::CONFIRMATION_PHRASE
                .' after deploying the HtmlSanitizer UTF-8 fix.'
            );
        }

        return self::SUCCESS;
    }
}

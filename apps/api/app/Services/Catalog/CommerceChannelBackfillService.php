<?php

namespace App\Services\Catalog;

use App\Enums\CommerceChannelCode;
use App\Models\CommerceChannel;
use App\Models\Product;
use App\Models\ProductChannelBackfillLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommerceChannelBackfillService
{
    public function __construct(
        private readonly CommerceChannelAuditor $auditor,
    ) {}

    /**
     * @param  array{dry_run?: bool}  $options
     * @return array{
     *     batch_id: string,
     *     dry_run: bool,
     *     assigned: int,
     *     skipped: int,
     *     rows: list<array{product_id: string, action: string, reason: string|null, assigned_channel_id: string|null}>
     * }
     */
    public function backfill(array $options = []): array
    {
        $dryRun = (bool) ($options['dry_run'] ?? true);
        $chinaChannel = CommerceChannel::query()
            ->where('code', CommerceChannelCode::ChinaImport->value)
            ->where('is_active', true)
            ->firstOrFail();

        $audit = $this->auditor->audit();
        $batchId = (string) Str::uuid();
        $assigned = 0;
        $skipped = 0;
        $rows = [];

        DB::transaction(function () use (
            $audit,
            $batchId,
            $dryRun,
            $chinaChannel,
            &$assigned,
            &$skipped,
            &$rows,
        ): void {
            foreach ($audit['products'] as $productRow) {
                $product = Product::query()->find($productRow['id']);

                if ($product === null) {
                    $skipped++;
                    $rows[] = [
                        'product_id' => $productRow['id'],
                        'action' => ProductChannelBackfillLog::ACTION_SKIPPED,
                        'reason' => 'product_not_found',
                        'assigned_channel_id' => null,
                    ];

                    continue;
                }

                if (filled($product->commerce_channel_id)) {
                    $skipped++;
                    $rows[] = [
                        'product_id' => $product->id,
                        'action' => ProductChannelBackfillLog::ACTION_SKIPPED,
                        'reason' => 'commerce_channel_already_assigned',
                        'assigned_channel_id' => null,
                    ];

                    if (! $dryRun) {
                        ProductChannelBackfillLog::query()->create([
                            'batch_id' => $batchId,
                            'product_id' => $product->id,
                            'previous_channel_id' => $product->commerce_channel_id,
                            'assigned_channel_id' => null,
                            'action' => ProductChannelBackfillLog::ACTION_SKIPPED,
                            'reason' => 'commerce_channel_already_assigned',
                        ]);
                    }

                    continue;
                }

                if ($product->fulfillment_source !== CommerceChannelCode::ChinaImport->fulfillmentSource()) {
                    $skipped++;
                    $rows[] = [
                        'product_id' => $product->id,
                        'action' => ProductChannelBackfillLog::ACTION_SKIPPED,
                        'reason' => 'unsupported_fulfillment_source',
                        'assigned_channel_id' => null,
                    ];

                    continue;
                }

                $assigned++;
                $rows[] = [
                    'product_id' => $product->id,
                    'action' => ProductChannelBackfillLog::ACTION_ASSIGNED,
                    'reason' => 'legacy_imported_from_china',
                    'assigned_channel_id' => $chinaChannel->id,
                ];

                if ($dryRun) {
                    continue;
                }

                $previousChannelId = $product->commerce_channel_id;
                $executedAt = now();

                $product->update([
                    'commerce_channel_id' => $chinaChannel->id,
                ]);

                ProductChannelBackfillLog::query()->create([
                    'batch_id' => $batchId,
                    'product_id' => $product->id,
                    'previous_channel_id' => $previousChannelId,
                    'assigned_channel_id' => $chinaChannel->id,
                    'action' => ProductChannelBackfillLog::ACTION_ASSIGNED,
                    'reason' => 'legacy_imported_from_china',
                    'executed_at' => $executedAt,
                ]);
            }
        });

        return [
            'batch_id' => $batchId,
            'dry_run' => $dryRun,
            'assigned' => $assigned,
            'skipped' => $skipped,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{restored: int, skipped: int}
     */
    public function rollback(string $batchId, bool $dryRun = true): array
    {
        $logs = ProductChannelBackfillLog::query()
            ->where('batch_id', $batchId)
            ->where('action', ProductChannelBackfillLog::ACTION_ASSIGNED)
            ->where('rolled_back', false)
            ->orderBy('created_at')
            ->get();

        if ($logs->isEmpty()) {
            throw ValidationException::withMessages([
                'batch_id' => ['No reversible assignments found for this batch.'],
            ]);
        }

        $restored = 0;
        $skipped = 0;

        DB::transaction(function () use ($logs, $batchId, $dryRun, &$restored, &$skipped): void {
            foreach ($logs as $log) {
                $product = Product::query()->find($log->product_id);
                if ($product === null) {
                    $skipped++;

                    continue;
                }

                if (! $dryRun) {
                    $product->update([
                        'commerce_channel_id' => $log->previous_channel_id,
                    ]);

                    $log->update([
                        'rolled_back' => true,
                        'rolled_back_at' => now(),
                    ]);

                    ProductChannelBackfillLog::query()->create([
                        'batch_id' => $batchId,
                        'product_id' => $log->product_id,
                        'previous_channel_id' => $log->assigned_channel_id,
                        'assigned_channel_id' => $log->previous_channel_id,
                        'action' => ProductChannelBackfillLog::ACTION_ROLLED_BACK,
                        'reason' => 'Rollback of batch '.$batchId,
                        'executed_at' => now(),
                    ]);
                }

                $restored++;
            }
        });

        return [
            'restored' => $restored,
            'skipped' => $skipped,
        ];
    }
}

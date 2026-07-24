<?php

namespace App\Services\Catalog;

use App\Enums\CommerceChannelCode;
use App\Models\CommerceChannel;
use App\Models\Product;
use App\Models\ProductShippingBackfillLog;
use App\Models\ProductShippingOption;
use App\Services\ProductShipping\ProductShippingOptionEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShippingOptionsBackfillService
{
    public function __construct(
        private readonly ShippingOptionsBackfillAuditor $auditor,
        private readonly ProductShippingOptionEngine $shippingOptionEngine,
    ) {}

    /**
     * @param  array{dry_run?: bool}  $options
     * @return array{
     *     batch_id: string,
     *     dry_run: bool,
     *     backfilled: int,
     *     skipped: int,
     *     rows: list<array{
     *         product_id: string,
     *         action: string,
     *         reason: string|null,
     *         created_option_ids: list<string>,
     *         planned_modes: list<string>
     *     }>
     * }
     */
    public function backfill(array $options = []): array
    {
        $dryRun = (bool) ($options['dry_run'] ?? true);
        $audit = $this->auditor->audit();
        $batchId = (string) Str::uuid();
        $backfilled = 0;
        $skipped = 0;
        $rows = [];

        DB::transaction(function () use (
            $audit,
            $batchId,
            $dryRun,
            &$backfilled,
            &$skipped,
            &$rows,
        ): void {
            foreach ($audit['products'] as $productRow) {
                $product = Product::query()
                    ->with('shippingOptions')
                    ->find($productRow['id']);

                $decision = $this->resolveDecision($product);

                if ($decision['action'] === ProductShippingBackfillLog::ACTION_BACKFILLED) {
                    $backfilled++;
                } else {
                    $skipped++;
                }

                $rows[] = [
                    'product_id' => $productRow['id'],
                    'action' => $decision['action'],
                    'reason' => $decision['reason'],
                    'created_option_ids' => $decision['created_option_ids'],
                    'planned_modes' => $decision['planned_modes'],
                ];

                if ($dryRun || $decision['action'] !== ProductShippingBackfillLog::ACTION_BACKFILLED || $product === null) {
                    if (! $dryRun && $decision['action'] === ProductShippingBackfillLog::ACTION_SKIPPED) {
                        ProductShippingBackfillLog::query()->create([
                            'batch_id' => $batchId,
                            'product_id' => $productRow['id'],
                            'created_option_ids' => [],
                            'previous_state' => null,
                            'action' => ProductShippingBackfillLog::ACTION_SKIPPED,
                            'reason' => $decision['reason'],
                        ]);
                    }

                    continue;
                }

                $previousState = $this->capturePreviousState($product);
                $beforeOptionIds = $product->shippingOptions->pluck('id')->all();
                $executedAt = now();

                $this->shippingOptionEngine->syncForProduct(
                    $product->fresh() ?? $product,
                    $decision['rows'],
                );

                $product->refresh()->load('shippingOptions');
                $createdOptionIds = $product->shippingOptions
                    ->whereNotIn('id', $beforeOptionIds)
                    ->pluck('id')
                    ->values()
                    ->all();

                ProductShippingBackfillLog::query()->create([
                    'batch_id' => $batchId,
                    'product_id' => $product->id,
                    'created_option_ids' => $createdOptionIds,
                    'previous_state' => $previousState,
                    'action' => ProductShippingBackfillLog::ACTION_BACKFILLED,
                    'reason' => 'legacy_air_sea_columns',
                    'executed_at' => $executedAt,
                ]);
            }
        });

        return [
            'batch_id' => $batchId,
            'dry_run' => $dryRun,
            'backfilled' => $backfilled,
            'skipped' => $skipped,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{restored: int, skipped: int}
     */
    public function rollback(string $batchId, bool $dryRun = true): array
    {
        $logs = ProductShippingBackfillLog::query()
            ->where('batch_id', $batchId)
            ->where('action', ProductShippingBackfillLog::ACTION_BACKFILLED)
            ->where('rolled_back', false)
            ->orderBy('created_at')
            ->get();

        if ($logs->isEmpty()) {
            throw ValidationException::withMessages([
                'batch_id' => ['No reversible shipping backfill entries found for this batch.'],
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
                    $this->restorePreviousState($product, $log);
                    $log->update([
                        'rolled_back' => true,
                        'rolled_back_at' => now(),
                    ]);

                    ProductShippingBackfillLog::query()->create([
                        'batch_id' => $batchId,
                        'product_id' => $log->product_id,
                        'created_option_ids' => $log->created_option_ids,
                        'previous_state' => $log->previous_state,
                        'action' => ProductShippingBackfillLog::ACTION_ROLLED_BACK,
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

    /**
     * @return array{
     *     action: string,
     *     reason: string|null,
     *     rows: list<array<string, mixed>>,
     *     planned_modes: list<string>,
     *     created_option_ids: list<string>
     * }
     */
    private function resolveDecision(?Product $product): array
    {
        if ($product === null) {
            return $this->skipDecision('product_not_found');
        }

        $chinaChannelId = CommerceChannel::query()
            ->where('code', CommerceChannelCode::ChinaImport->value)
            ->value('id');

        if ($product->commerce_channel_id === null) {
            return $this->skipDecision('commerce_channel_missing');
        }

        if (! filled($chinaChannelId) || $product->commerce_channel_id !== $chinaChannelId) {
            return $this->skipDecision('not_china_import_channel');
        }

        if ($this->shippingOptionEngine->hasPublishableShippingOption($product)) {
            return $this->skipDecision('publishable_shipping_option_exists');
        }

        $rows = $this->auditor->buildLegacyRows($product);
        if ($rows === []) {
            return $this->skipDecision('no_legacy_shipping_prices');
        }

        return [
            'action' => ProductShippingBackfillLog::ACTION_BACKFILLED,
            'reason' => 'legacy_air_sea_columns',
            'rows' => $rows,
            'planned_modes' => array_map(fn (array $row) => (string) $row['transport_mode'], $rows),
            'created_option_ids' => [],
        ];
    }

    /**
     * @return array{
     *     air_shipping_price: string|null,
     *     sea_shipping_price: string|null,
     *     shipping_options: list<array<string, mixed>>
     * }
     */
    private function capturePreviousState(Product $product): array
    {
        return [
            'air_shipping_price' => $product->air_shipping_price !== null
                ? (string) $product->air_shipping_price
                : null,
            'sea_shipping_price' => $product->sea_shipping_price !== null
                ? (string) $product->sea_shipping_price
                : null,
            'shipping_options' => $product->shippingOptions
                ->map(fn (ProductShippingOption $option) => $this->snapshotOption($option))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotOption(ProductShippingOption $option): array
    {
        return [
            'id' => $option->id,
            'transport_mode' => $option->transport_mode instanceof \BackedEnum
                ? $option->transport_mode->value
                : (string) $option->transport_mode,
            'price' => (string) $option->price,
            'currency' => $option->currency,
            'is_available' => (bool) $option->is_available,
            'notes' => $option->notes,
            'sort_order' => (int) $option->sort_order,
        ];
    }

    private function restorePreviousState(Product $product, ProductShippingBackfillLog $log): void
    {
        $previousState = is_array($log->previous_state) ? $log->previous_state : [];
        $createdOptionIds = is_array($log->created_option_ids) ? $log->created_option_ids : [];

        foreach ($createdOptionIds as $optionId) {
            $option = ProductShippingOption::withTrashed()->find($optionId);
            if ($option !== null && $option->product_id === $product->id) {
                $option->delete();
            }
        }

        foreach ($previousState['shipping_options'] ?? [] as $snapshot) {
            if (! is_array($snapshot) || ! filled($snapshot['id'] ?? null)) {
                continue;
            }

            if (in_array($snapshot['id'], $createdOptionIds, true)) {
                continue;
            }

            /** @var ProductShippingOption|null $existing */
            $existing = ProductShippingOption::withTrashed()->find($snapshot['id']);
            if ($existing === null || $existing->product_id !== $product->id) {
                continue;
            }

            if ($existing->trashed()) {
                $existing->restore();
            }

            $existing->fill([
                'transport_mode' => $snapshot['transport_mode'],
                'price' => $snapshot['price'],
                'currency' => $snapshot['currency'] ?? 'TZS',
                'is_available' => (bool) ($snapshot['is_available'] ?? true),
                'notes' => $snapshot['notes'] ?? null,
                'sort_order' => (int) ($snapshot['sort_order'] ?? 0),
            ])->save();
        }

        $this->shippingOptionEngine->syncLegacyColumns($product->fresh() ?? $product);
    }

    /**
     * @return array{
     *     action: string,
     *     reason: string|null,
     *     rows: list<array<string, mixed>>,
     *     planned_modes: list<string>,
     *     created_option_ids: list<string>
     * }
     */
    private function skipDecision(string $reason): array
    {
        return [
            'action' => ProductShippingBackfillLog::ACTION_SKIPPED,
            'reason' => $reason,
            'rows' => [],
            'planned_modes' => [],
            'created_option_ids' => [],
        ];
    }
}

<?php

namespace App\Services\Catalog;

use App\Enums\ProductLifecycleStatus;
use App\Models\Product;
use App\Models\ProductStoreBackfillLog;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TzLocalStoreOwnershipBackfill
{
    public function __construct(
        private readonly TzLocalStoreOwnershipAuditor $auditor,
    ) {}

    /**
     * @param  array{
     *     dry_run?: bool,
     *     store_id?: string|null,
     *     include_listed?: bool,
     *     include_without_category_store?: bool,
     * }  $options
     * @return array{
     *     batch_id: string,
     *     dry_run: bool,
     *     assigned: int,
     *     skipped: int,
     *     rows: list<array{product_id: string, action: string, reason: string|null, assigned_store_id: string|null}>
     * }
     */
    public function backfill(array $options = []): array
    {
        $dryRun = (bool) ($options['dry_run'] ?? true);
        $explicitStoreId = filled($options['store_id'] ?? null) ? (string) $options['store_id'] : null;
        $includeListed = (bool) ($options['include_listed'] ?? false);
        $includeWithoutCategoryStore = (bool) ($options['include_without_category_store'] ?? false);

        if ($explicitStoreId !== null) {
            Store::query()->whereKey($explicitStoreId)->where('is_active', true)->firstOrFail();
        }

        $audit = $this->auditor->audit();
        $batchId = (string) Str::uuid();
        $assigned = 0;
        $skipped = 0;
        $rows = [];

        DB::transaction(function () use (
            $audit,
            $batchId,
            $dryRun,
            $explicitStoreId,
            $includeListed,
            $includeWithoutCategoryStore,
            &$assigned,
            &$skipped,
            &$rows,
        ): void {
            foreach ($audit['products'] as $productRow) {
                $decision = $this->resolveAssignmentDecision(
                    $productRow,
                    $explicitStoreId,
                    $includeListed,
                    $includeWithoutCategoryStore,
                );

                if ($decision['action'] === ProductStoreBackfillLog::ACTION_ASSIGNED) {
                    $assigned++;
                } else {
                    $skipped++;
                }

                $rows[] = [
                    'product_id' => $productRow['id'],
                    'action' => $decision['action'],
                    'reason' => $decision['reason'],
                    'assigned_store_id' => $decision['store_id'],
                ];

                if ($dryRun || $decision['action'] !== ProductStoreBackfillLog::ACTION_ASSIGNED) {
                    if (! $dryRun && $decision['action'] === ProductStoreBackfillLog::ACTION_SKIPPED) {
                        ProductStoreBackfillLog::query()->create([
                            'batch_id' => $batchId,
                            'product_id' => $productRow['id'],
                            'previous_store_id' => null,
                            'assigned_store_id' => null,
                            'action' => ProductStoreBackfillLog::ACTION_SKIPPED,
                            'reason' => $decision['reason'],
                            'lifecycle_status' => $productRow['lifecycle_status'],
                        ]);
                    }

                    continue;
                }

                $product = Product::query()->findOrFail($productRow['id']);
                $previousStoreId = $product->store_id;

                $product->update([
                    'store_id' => $decision['store_id'],
                ]);

                ProductStoreBackfillLog::query()->create([
                    'batch_id' => $batchId,
                    'product_id' => $product->id,
                    'previous_store_id' => $previousStoreId,
                    'assigned_store_id' => $decision['store_id'],
                    'action' => ProductStoreBackfillLog::ACTION_ASSIGNED,
                    'reason' => $decision['reason'],
                    'lifecycle_status' => $productRow['lifecycle_status'],
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
        $logs = ProductStoreBackfillLog::query()
            ->where('batch_id', $batchId)
            ->where('action', ProductStoreBackfillLog::ACTION_ASSIGNED)
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
                        'store_id' => $log->previous_store_id,
                    ]);

                    $log->update([
                        'rolled_back' => true,
                        'rolled_back_at' => now(),
                    ]);

                    ProductStoreBackfillLog::query()->create([
                        'batch_id' => $batchId,
                        'product_id' => $log->product_id,
                        'previous_store_id' => $log->assigned_store_id,
                        'assigned_store_id' => $log->previous_store_id,
                        'action' => ProductStoreBackfillLog::ACTION_ROLLED_BACK,
                        'reason' => 'Rollback of batch '.$batchId,
                        'lifecycle_status' => $log->lifecycle_status,
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
     * @param  array{
     *     id: string,
     *     lifecycle_status: string,
     *     suggested_store_id: string|null,
     *     auto_assign_eligible: bool,
     *     manual_assignment_required: bool,
     * }  $productRow
     * @return array{action: string, reason: string|null, store_id: string|null}
     */
    private function resolveAssignmentDecision(
        array $productRow,
        ?string $explicitStoreId,
        bool $includeListed,
        bool $includeWithoutCategoryStore,
    ): array {
        $lifecycle = ProductLifecycleStatus::tryFromMixed($productRow['lifecycle_status']);
        $listed = in_array($lifecycle, [ProductLifecycleStatus::Active, ProductLifecycleStatus::OutOfStock], true);

        if ($listed && ! $includeListed) {
            return $this->skipDecision('manual_assignment_required_listed_product');
        }

        if ($explicitStoreId !== null) {
            if (
                ! $includeWithoutCategoryStore
                && ! filled($productRow['suggested_store_id'])
            ) {
                return $this->skipDecision('manual_assignment_required_pass_include_without_category_store');
            }

            return $this->assignDecision($explicitStoreId, 'explicit_store_id');
        }

        if (! filled($productRow['suggested_store_id'])) {
            return $this->skipDecision('manual_assignment_required_no_category_store');
        }

        return $this->assignDecision(
            (string) $productRow['suggested_store_id'],
            'inferred_from_category_store_id',
        );
    }

    /**
     * @return array{action: string, reason: string|null, store_id: string|null}
     */
    private function assignDecision(string $storeId, string $reason): array
    {
        return [
            'action' => ProductStoreBackfillLog::ACTION_ASSIGNED,
            'reason' => $reason,
            'store_id' => $storeId,
        ];
    }

    /**
     * @return array{action: string, reason: string|null, store_id: string|null}
     */
    private function skipDecision(string $reason): array
    {
        return [
            'action' => ProductStoreBackfillLog::ACTION_SKIPPED,
            'reason' => $reason,
            'store_id' => null,
        ];
    }
}

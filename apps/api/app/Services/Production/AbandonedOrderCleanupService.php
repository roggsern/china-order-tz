<?php

namespace App\Services\Production;

use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Inventory\OrderInventoryRestockService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Selective production cleanup: delete abandoned / QA orders by id while keeping
 * a named paid order and all customer accounts.
 */
class AbandonedOrderCleanupService
{
    private const CHUNK_SIZE = 500;

    public function __construct(
        private readonly OrderInventoryRestockService $inventoryRestock,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(string $keepOrderNumber): array
    {
        $this->assertAllowedEnvironment();
        $this->assertManifestSafety();
        $this->assertDeletionPlanSchema();

        $protected = $this->assertProtectedOrder($keepOrderNumber);
        $candidates = $this->selectCandidates((string) $protected['id']);
        $this->assertCandidatesSafe($candidates);

        $candidateIds = $candidates->pluck('id')->map(fn ($id) => (string) $id)->all();
        $paymentTxns = $this->listPaymentTransactionsForOrders($candidateIds);
        $dependencyCounts = $this->countOrderBoundDependencies($candidateIds);
        $inventorySnapshot = $this->inventoryIntegritySnapshot();

        return [
            'environment' => (string) app()->environment(),
            'database_host' => (string) config('database.connections.'.config('database.default').'.host'),
            'database_name' => (string) config('database.connections.'.config('database.default').'.database'),
            'keep_order_number' => $keepOrderNumber,
            'protected_order' => $protected,
            'delete_candidates' => $candidates->map(fn ($row) => [
                'id' => (string) $row->id,
                'order_number' => (string) ($row->order_number ?? ''),
                'customer_email' => $row->customer_email !== null ? (string) $row->customer_email : null,
                'status' => (string) ($row->status ?? ''),
                'total' => (string) ($row->total ?? '0'),
            ])->all(),
            'payment_transactions_to_delete' => $paymentTxns,
            'dependency_counts' => $dependencyCounts,
            'preserve_counts' => $this->preserveReport($inventorySnapshot),
            'inventory_note' => 'Unpaid abandoned orders: release checkout reservation holds via '
                .'OrderInventoryRestockService (reserved → available). on_hand is not decremented for unpaid orders. '
                .'Paid/fulfilled orders are never deleted, so committed stock is never restocked by this command.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(string $keepOrderNumber): array
    {
        $this->assertAllowedEnvironment();
        $this->assertManifestSafety();
        $this->assertDeletionPlanSchema();

        $protectedBefore = $this->assertProtectedOrder($keepOrderNumber);
        $candidates = $this->selectCandidates((string) $protectedBefore['id']);
        $this->assertCandidatesSafe($candidates);

        $candidateIds = $candidates->pluck('id')->map(fn ($id) => (string) $id)->all();
        $preserveBefore = $this->preserveReport($this->inventoryIntegritySnapshot());
        $deletedTables = [];
        $inventoryReleased = 0;

        try {
            DB::transaction(function () use (
                $keepOrderNumber,
                $candidateIds,
                &$deletedTables,
                &$inventoryReleased,
            ): void {
                // Re-assert immediately before writes.
                $this->assertProtectedOrder($keepOrderNumber);

                $freshCandidates = $this->selectCandidates(
                    (string) Order::query()->where('order_number', $keepOrderNumber)->value('id'),
                );
                $this->assertCandidatesSafe($freshCandidates);
                $ids = $freshCandidates->pluck('id')->map(fn ($id) => (string) $id)->all();

                if ($ids !== $candidateIds) {
                    throw new RuntimeException(
                        'Abandoned-order cleanup aborted: candidate set changed between preview and execution.',
                    );
                }

                foreach ($ids as $orderId) {
                    $order = Order::query()->with(['items', 'checkoutSession.cart'])->find($orderId);
                    if ($order === null) {
                        continue;
                    }
                    // Domain release: unpaid checkout holds → reserved becomes available. Never invents stock math.
                    $this->inventoryRestock->releaseCheckoutHoldsIfPresent($order);
                    $inventoryReleased++;
                }

                $deletedTables = $this->deleteOrderBoundGraph($ids);

                if (app()->environment('testing')
                    && config('testing.fail_abandoned_order_cleanup_after') === 'orders') {
                    throw new RuntimeException('Forced cleanup failure for rollback test.');
                }
            });
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Abandoned-order cleanup aborted and rolled back: '.$e->getMessage(),
                previous: $e,
            );
        }

        $protectedAfter = $this->assertProtectedOrder($keepOrderNumber);
        $remainingCandidates = $this->selectCandidates((string) $protectedAfter['id']);
        if ($remainingCandidates->isNotEmpty()) {
            throw new RuntimeException(
                'Post-cleanup verification failed: abandoned candidates still remain: '
                .$remainingCandidates->pluck('order_number')->implode(', '),
            );
        }

        $preserveAfter = $this->preserveReport($this->inventoryIntegritySnapshot());
        $catalogDrift = $this->detectCatalogDrift($preserveBefore, $preserveAfter);

        if ($catalogDrift !== []) {
            throw new RuntimeException(
                'Post-cleanup verification failed: catalog/identity drift: '.json_encode($catalogDrift),
            );
        }

        if ((int) ($preserveAfter['users'] ?? 0) !== (int) ($preserveBefore['users'] ?? 0)) {
            throw new RuntimeException('Post-cleanup verification failed: user count changed.');
        }

        $successfulTxnStillExists = Schema::hasTable('payment_transactions')
            && DB::table('payment_transactions')
                ->where('order_id', $protectedAfter['id'])
                ->where('status', PaymentTransactionStatus::Successful->value)
                ->exists();

        if (! $successfulTxnStillExists) {
            throw new RuntimeException(
                'Post-cleanup verification failed: protected order successful payment transaction missing.',
            );
        }

        return [
            'deleted_tables' => $deletedTables,
            'candidate_ids_deleted' => $candidateIds,
            'inventory_release_attempts' => $inventoryReleased,
            'protected_order' => $protectedAfter,
            'preserve_counts_before' => $preserveBefore,
            'preserve_counts' => $preserveAfter,
            'catalog_drift' => $catalogDrift,
        ];
    }

    public function assertAllowedEnvironment(): void
    {
        if (! app()->environment(['production', 'testing'])) {
            throw new RuntimeException(
                'Abandoned-order cleanup aborted: environment must be production (or testing for PHPUnit). Current: '
                .app()->environment(),
            );
        }
    }

    public function assertManifestSafety(): void
    {
        $overlap = array_values(array_intersect(
            array_keys($this->deletionPlan()),
            AbandonedOrderCleanupManifest::FORBIDDEN_DELETE_TABLES,
        ));

        if ($overlap !== []) {
            throw new RuntimeException(
                'Abandoned-order cleanup aborted: forbidden tables appear in deletion plan: '
                .implode(', ', $overlap),
            );
        }
    }

    /**
     * Validate every mapped table against the live schema before any count/delete SQL.
     * Missing expected columns abort loudly — never fall back to whole-table deletes.
     */
    public function assertDeletionPlanSchema(): void
    {
        foreach ($this->deletionPlan() as $table => $plan) {
            $this->assertTableMapping($table, $plan);
        }
    }

    /**
     * @param  array{strategy: string, column?: string, parent?: string, parent_fk?: string, parent_order_col?: string, morph_type?: string, morph_id?: string, json_path?: string}  $plan
     */
    public function assertTableMapping(string $table, array $plan): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $strategy = $plan['strategy'] ?? '';

        match ($strategy) {
            'order_id' => $this->requireColumn(
                $table,
                $plan['column'] ?? 'order_id',
                'DIRECT_ORDER_ID',
            ),
            'order_item_id' => $this->assertOrderItemIdMapping($table, $plan['column'] ?? 'order_item_id'),
            'via_parent' => $this->assertViaParentMapping($table, $plan),
            'morph' => $this->assertMorphMapping($table, $plan),
            'morph_reference' => $this->assertMorphReferenceMapping($table, $plan),
            'json_order_id' => $this->requireColumn($table, 'data', 'MORPH/PAYLOAD (JSON order_id)'),
            'hard_delete_orders' => $this->requireColumn($table, 'id', 'HARD_DELETE_ORDERS'),
            default => throw new RuntimeException(
                "Abandoned-order cleanup aborted: unknown mapping strategy [{$strategy}] for table [{$table}]. "
                .'Refusing unsafe fallback.',
            ),
        };
    }

    private function requireColumn(string $table, string $column, string $mappingKind): void
    {
        if (! Schema::hasColumn($table, $column)) {
            throw new RuntimeException(
                "Abandoned-order cleanup aborted: table [{$table}] mapping ({$mappingKind}) expects column [{$column}] "
                .'but it is missing on the live schema. Refusing unsafe fallback (no whole-table delete).',
            );
        }
    }

    private function assertOrderItemIdMapping(string $table, string $column): void
    {
        $this->requireColumn($table, $column, 'VIA_ORDER_ITEM_ID');

        if (! Schema::hasTable('order_items')) {
            throw new RuntimeException(
                "Abandoned-order cleanup aborted: table [{$table}] mapping (VIA_ORDER_ITEM_ID) requires "
                .'[order_items] which is missing. Refusing unsafe fallback.',
            );
        }

        $this->requireColumn('order_items', 'order_id', 'VIA_ORDER_ITEM_ID parent');
        $this->requireColumn('order_items', 'id', 'VIA_ORDER_ITEM_ID parent');
    }

    /**
     * @param  array{parent?: string, parent_fk?: string, parent_order_col?: string}  $plan
     */
    private function assertViaParentMapping(string $table, array $plan): void
    {
        $parent = $plan['parent'] ?? '';
        $fk = $plan['parent_fk'] ?? '';
        $parentOrderCol = $plan['parent_order_col'] ?? 'order_id';

        if ($parent === '' || $fk === '') {
            throw new RuntimeException(
                "Abandoned-order cleanup aborted: table [{$table}] VIA_OTHER_PARENT mapping is incomplete "
                .'(parent/parent_fk). Refusing unsafe fallback.',
            );
        }

        if (! Schema::hasTable($parent)) {
            throw new RuntimeException(
                "Abandoned-order cleanup aborted: table [{$table}] VIA_OTHER_PARENT mapping requires parent "
                ."[{$parent}] which is missing. Refusing unsafe fallback.",
            );
        }

        $this->requireColumn($table, $fk, "VIA_OTHER_PARENT via {$parent}");
        $this->requireColumn($parent, $parentOrderCol, "VIA_OTHER_PARENT parent {$parent}");
        $this->requireColumn($parent, 'id', "VIA_OTHER_PARENT parent {$parent}");
    }

    /**
     * @param  array{morph_type?: string, morph_id?: string}  $plan
     */
    private function assertMorphMapping(string $table, array $plan): void
    {
        $typeCol = $plan['morph_type'] ?? '';
        $idCol = $plan['morph_id'] ?? '';
        if ($typeCol === '' || $idCol === '') {
            throw new RuntimeException(
                "Abandoned-order cleanup aborted: table [{$table}] MORPH mapping is incomplete. "
                .'Refusing unsafe fallback.',
            );
        }

        $this->requireColumn($table, $typeCol, 'MORPH/PAYLOAD');
        $this->requireColumn($table, $idCol, 'MORPH/PAYLOAD');
    }

    /**
     * @param  array{morph_type?: string, morph_id?: string}  $plan
     */
    private function assertMorphReferenceMapping(string $table, array $plan): void
    {
        $this->assertMorphMapping($table, [
            'morph_type' => $plan['morph_type'] ?? 'reference_type',
            'morph_id' => $plan['morph_id'] ?? 'reference_id',
        ]);

        if (! Schema::hasTable('order_items') || ! Schema::hasColumn('order_items', 'order_id')) {
            throw new RuntimeException(
                "Abandoned-order cleanup aborted: table [{$table}] morph_reference mapping requires "
                .'[order_items.order_id]. Refusing unsafe fallback.',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function assertProtectedOrder(string $keepOrderNumber): array
    {
        $keepOrderNumber = trim($keepOrderNumber);
        if ($keepOrderNumber === '') {
            throw new RuntimeException('Abandoned-order cleanup aborted: --keep-order is required.');
        }

        if (! Schema::hasTable('orders')) {
            throw new RuntimeException('Abandoned-order cleanup aborted: orders table missing.');
        }

        $order = DB::table('orders')->where('order_number', $keepOrderNumber)->first();
        if ($order === null) {
            throw new RuntimeException(
                'Abandoned-order cleanup aborted: protected order not found: '.$keepOrderNumber,
            );
        }

        $status = (string) ($order->status ?? '');
        if ($status !== OrderStatus::Paid->value) {
            throw new RuntimeException(sprintf(
                'Abandoned-order cleanup aborted: protected order %s status must be paid, got [%s].',
                $keepOrderNumber,
                $status,
            ));
        }

        $successfulTxn = null;
        if (Schema::hasTable('payment_transactions')) {
            $successfulTxn = DB::table('payment_transactions')
                ->where('order_id', $order->id)
                ->where('status', PaymentTransactionStatus::Successful->value)
                ->orderBy('completed_at')
                ->first();
        }

        if ($successfulTxn === null) {
            throw new RuntimeException(sprintf(
                'Abandoned-order cleanup aborted: protected order %s has no successful payment transaction.',
                $keepOrderNumber,
            ));
        }

        $email = null;
        if (Schema::hasTable('users') && $order->user_id) {
            $email = DB::table('users')->where('id', $order->user_id)->value('email');
        }

        return [
            'id' => (string) $order->id,
            'order_number' => (string) $order->order_number,
            'customer_email' => $email !== null ? (string) $email : null,
            'status' => $status,
            'total' => (string) ($order->total ?? '0'),
            'payment_transaction_id' => (string) $successfulTxn->id,
            'payment_status' => (string) $successfulTxn->status,
            'merchant_reference' => isset($successfulTxn->merchant_reference)
                ? (string) $successfulTxn->merchant_reference
                : null,
            'completed_at' => isset($successfulTxn->completed_at)
                ? (string) $successfulTxn->completed_at
                : null,
        ];
    }

    /**
     * @return Collection<int, object>
     */
    public function selectCandidates(string $keepOrderId): Collection
    {
        $query = DB::table('orders')
            ->leftJoin('users', 'users.id', '=', 'orders.user_id')
            ->where('orders.id', '!=', $keepOrderId)
            ->whereNull('orders.deleted_at')
            ->orderBy('orders.created_at')
            ->select([
                'orders.id',
                'orders.order_number',
                'orders.status',
                'orders.total',
                'orders.user_id',
                'users.email as customer_email',
            ]);

        return $query->get();
    }

    /**
     * @param  Collection<int, object>  $candidates
     */
    public function assertCandidatesSafe(Collection $candidates): void
    {
        if ($candidates->isEmpty()) {
            return;
        }

        $violations = [];

        foreach ($candidates as $row) {
            $status = (string) ($row->status ?? '');
            if (in_array($status, AbandonedOrderCleanupManifest::PROTECTED_ORDER_STATUSES, true)) {
                $violations[] = sprintf(
                    'order %s (%s) has protected status [%s]',
                    $row->order_number ?? $row->id,
                    $row->id,
                    $status,
                );
            }
        }

        $ids = $candidates->pluck('id')->all();
        if (Schema::hasTable('payment_transactions') && $ids !== []) {
            $badTxns = DB::table('payment_transactions')
                ->whereIn('order_id', $ids)
                ->whereIn('status', AbandonedOrderCleanupManifest::PROTECTED_PAYMENT_TRANSACTION_STATUSES)
                ->get(['id', 'order_id', 'merchant_reference', 'status']);

            foreach ($badTxns as $txn) {
                $violations[] = sprintf(
                    'payment transaction %s (order %s, ref %s) has protected status [%s]',
                    $txn->id,
                    $txn->order_id,
                    $txn->merchant_reference ?? 'n/a',
                    $txn->status,
                );
            }
        }

        if ($violations !== []) {
            throw new RuntimeException(
                "Abandoned-order cleanup aborted: protected candidates detected — NO WRITES.\n- "
                .implode("\n- ", $violations),
            );
        }
    }

    /**
     * @param  list<string>  $orderIds
     * @return list<array<string, mixed>>
     */
    private function listPaymentTransactionsForOrders(array $orderIds): array
    {
        if ($orderIds === [] || ! Schema::hasTable('payment_transactions')) {
            return [];
        }

        $cols = ['id', 'order_id', 'merchant_reference', 'status'];
        if (Schema::hasColumn('payment_transactions', 'callback_received_at')) {
            $cols[] = 'callback_received_at';
        }
        if (Schema::hasColumn('payment_transactions', 'completed_at')) {
            $cols[] = 'completed_at';
        }

        return DB::table('payment_transactions')
            ->whereIn('order_id', $orderIds)
            ->orderBy('created_at')
            ->get($cols)
            ->map(fn ($row) => [
                'id' => (string) $row->id,
                'order_id' => (string) $row->order_id,
                'merchant_reference' => isset($row->merchant_reference) ? (string) $row->merchant_reference : null,
                'status' => (string) ($row->status ?? ''),
                'callback_received' => isset($row->callback_received_at) && $row->callback_received_at !== null,
                'completed' => isset($row->completed_at) && $row->completed_at !== null,
                'callback_received_at' => isset($row->callback_received_at) && $row->callback_received_at !== null
                    ? (string) $row->callback_received_at
                    : null,
                'completed_at' => isset($row->completed_at) && $row->completed_at !== null
                    ? (string) $row->completed_at
                    : null,
            ])
            ->all();
    }

    /**
     * @param  list<string>  $orderIds
     * @return array<string, int>
     */
    private function countOrderBoundDependencies(array $orderIds): array
    {
        $counts = [];
        foreach ($this->deletionPlan() as $table => $plan) {
            $counts[$table] = $this->countForPlan($table, $plan, $orderIds);
        }

        return $counts;
    }

    /**
     * @param  list<string>  $orderIds
     * @return array<string, int>
     */
    private function deleteOrderBoundGraph(array $orderIds): array
    {
        $deleted = [];

        foreach ($this->deletionPlan() as $table => $plan) {
            if (app()->environment('testing')
                && config('testing.fail_abandoned_order_cleanup_after') === $table) {
                throw new RuntimeException('Forced cleanup failure for rollback test.');
            }

            $deleted[$table] = $this->deleteForPlan($table, $plan, $orderIds);
        }

        return $deleted;
    }

    /**
     * Child → parent deletion plan for order-scoped rows only.
     *
     * @return array<string, array{strategy: string, column?: string, parent?: string, parent_fk?: string, parent_order_col?: string, morph_type?: string, morph_id?: string, json_path?: string}>
     */
    private function deletionPlan(): array
    {
        return [
            'support_messages' => [
                'strategy' => 'via_parent',
                'parent' => 'support_tickets',
                'parent_fk' => 'ticket_id',
                'parent_order_col' => 'order_id',
            ],
            'support_tickets' => ['strategy' => 'order_id', 'column' => 'order_id'],

            'refund_transactions' => ['strategy' => 'order_id', 'column' => 'order_id'],
            'return_items' => [
                'strategy' => 'via_parent',
                'parent' => 'return_requests',
                'parent_fk' => 'return_request_id',
                'parent_order_col' => 'order_id',
            ],
            'return_requests' => ['strategy' => 'order_id', 'column' => 'order_id'],
            'refunds' => ['strategy' => 'order_id', 'column' => 'order_id'],

            'loyalty_redemptions' => ['strategy' => 'order_id', 'column' => 'order_id'],
            'loyalty_ledger_entries' => ['strategy' => 'order_id', 'column' => 'order_id'],
            'coupon_usages' => ['strategy' => 'order_id', 'column' => 'order_id'],
            'promotion_usages' => ['strategy' => 'order_id', 'column' => 'order_id'],
            'order_discount_snapshots' => ['strategy' => 'order_id', 'column' => 'order_id'],

            'warehouse_packing_lines' => [
                'strategy' => 'via_parent',
                'parent' => 'order_items',
                'parent_fk' => 'order_item_id',
                'parent_order_col' => 'order_id',
            ],
            'warehouse_pick_list_lines' => [
                'strategy' => 'via_parent',
                'parent' => 'order_items',
                'parent_fk' => 'order_item_id',
                'parent_order_col' => 'order_id',
            ],
            'warehouse_packing_records' => [
                'strategy' => 'via_parent',
                'parent' => 'warehouse_jobs',
                'parent_fk' => 'warehouse_job_id',
                'parent_order_col' => 'order_id',
            ],
            'warehouse_pick_lists' => ['strategy' => 'order_id', 'column' => 'order_id'],
            'customer_agent_pickup_histories' => ['strategy' => 'order_id', 'column' => 'order_id'],
            'customer_agent_pickups' => ['strategy' => 'order_id', 'column' => 'order_id'],
            'china_workflow_histories' => ['strategy' => 'order_id', 'column' => 'order_id'],
            'china_workflow_records' => ['strategy' => 'order_id', 'column' => 'order_id'],
            'china_procurement_requirement_links' => ['strategy' => 'order_id', 'column' => 'order_id'],
            'fulfillment_status_histories' => [
                'strategy' => 'via_parent',
                'parent' => 'fulfillments',
                'parent_fk' => 'fulfillment_id',
                'parent_order_col' => 'order_id',
            ],
            'pos_sale_idempotency_keys' => ['strategy' => 'order_id', 'column' => 'order_id'],
            'pos_receipts' => ['strategy' => 'order_id', 'column' => 'order_id'],

            'shipment_tracking_events' => [
                'strategy' => 'via_parent',
                'parent' => 'shipments',
                'parent_fk' => 'shipment_id',
                'parent_order_col' => 'order_id',
            ],
            'shipment_status_histories' => ['strategy' => 'order_id', 'column' => 'order_id'],
            'order_tracking_events' => ['strategy' => 'order_id', 'column' => 'order_id'],
            'order_status_history' => ['strategy' => 'order_id', 'column' => 'order_id'],
            // Production schema: order_cost_snapshots has order_item_id only (NO order_id).
            'order_cost_snapshots' => ['strategy' => 'order_item_id', 'column' => 'order_item_id'],
            'profit_records' => ['strategy' => 'order_id', 'column' => 'order_id'],

            'payment_transactions' => ['strategy' => 'order_id', 'column' => 'order_id'],
            'payments' => ['strategy' => 'order_id', 'column' => 'order_id'],
            'delivery_options' => ['strategy' => 'order_id', 'column' => 'order_id'],
            'warehouse_jobs' => ['strategy' => 'order_id', 'column' => 'order_id'],
            'shipments' => ['strategy' => 'order_id', 'column' => 'order_id'],
            'fulfillments' => ['strategy' => 'order_id', 'column' => 'order_id'],
            'inventory_stock_movements' => [
                'strategy' => 'morph_reference',
                'morph_type' => 'reference_type',
                'morph_id' => 'reference_id',
            ],
            'order_items' => ['strategy' => 'order_id', 'column' => 'order_id'],
            'shipping_addresses' => ['strategy' => 'order_id', 'column' => 'order_id'],

            'review_images' => [
                'strategy' => 'via_parent',
                'parent' => 'reviews',
                'parent_fk' => 'review_id',
                'parent_order_col' => 'order_id',
            ],
            'reviews' => ['strategy' => 'order_id', 'column' => 'order_id'],
            'activity_logs' => [
                'strategy' => 'morph',
                'morph_type' => 'subject_type',
                'morph_id' => 'subject_id',
            ],
            'audit_logs' => [
                'strategy' => 'morph',
                'morph_type' => 'auditable_type',
                'morph_id' => 'auditable_id',
            ],
            'notifications' => [
                'strategy' => 'json_order_id',
                'json_path' => 'data->order_id',
            ],

            'orders' => ['strategy' => 'hard_delete_orders'],
        ];
    }

    /**
     * @param  array{strategy: string, column?: string, parent?: string, parent_fk?: string, parent_order_col?: string, morph_type?: string, morph_id?: string, json_path?: string}  $plan
     * @param  list<string>  $orderIds
     */
    private function countForPlan(string $table, array $plan, array $orderIds): int
    {
        if ($orderIds === [] || ! Schema::hasTable($table)) {
            return 0;
        }

        $this->assertTableMapping($table, $plan);

        return match ($plan['strategy']) {
            'order_id' => (int) DB::table($table)->whereIn($plan['column'] ?? 'order_id', $orderIds)->count(),
            'order_item_id' => $this->countViaOrderItemIds($table, $plan['column'] ?? 'order_item_id', $orderIds),
            'via_parent' => $this->countViaParent($table, $plan, $orderIds),
            'morph' => (int) DB::table($table)
                ->where($plan['morph_type'], Order::class)
                ->whereIn($plan['morph_id'], $orderIds)
                ->count(),
            'morph_reference' => $this->countMorphReference($table, $plan, $orderIds),
            'json_order_id' => $this->countJsonOrderId($table, $plan['json_path'] ?? 'data->order_id', $orderIds),
            'hard_delete_orders' => count($orderIds),
            default => throw new RuntimeException(
                "Abandoned-order cleanup aborted: unknown strategy [{$plan['strategy']}] for [{$table}].",
            ),
        };
    }

    /**
     * @param  array{strategy: string, column?: string, parent?: string, parent_fk?: string, parent_order_col?: string, morph_type?: string, morph_id?: string, json_path?: string}  $plan
     * @param  list<string>  $orderIds
     */
    private function deleteForPlan(string $table, array $plan, array $orderIds): int
    {
        if ($orderIds === [] || ! Schema::hasTable($table)) {
            return 0;
        }

        $this->assertTableMapping($table, $plan);

        return match ($plan['strategy']) {
            'order_id' => $this->deleteWhereInChunked($table, $plan['column'] ?? 'order_id', $orderIds),
            'order_item_id' => $this->deleteViaOrderItemIds($table, $plan['column'] ?? 'order_item_id', $orderIds),
            'via_parent' => $this->deleteViaParent($table, $plan, $orderIds),
            'morph' => $this->deleteMorph($table, $plan, $orderIds),
            'morph_reference' => $this->deleteMorphReference($table, $plan, $orderIds),
            'json_order_id' => $this->deleteJsonOrderId($table, $plan['json_path'] ?? 'data->order_id', $orderIds),
            'hard_delete_orders' => $this->hardDeleteOrders($orderIds),
            default => throw new RuntimeException(
                "Abandoned-order cleanup aborted: unknown strategy [{$plan['strategy']}] for [{$table}].",
            ),
        };
    }

    /**
     * @param  list<string>  $orderIds
     * @return list<string>
     */
    private function orderItemIdsForOrders(array $orderIds): array
    {
        if ($orderIds === [] || ! Schema::hasTable('order_items')) {
            return [];
        }

        return DB::table('order_items')
            ->whereIn('order_id', $orderIds)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    /**
     * @param  list<string>  $orderIds
     */
    private function countViaOrderItemIds(string $table, string $column, array $orderIds): int
    {
        $itemIds = $this->orderItemIdsForOrders($orderIds);
        if ($itemIds === []) {
            return 0;
        }

        return (int) DB::table($table)->whereIn($column, $itemIds)->count();
    }

    /**
     * @param  list<string>  $orderIds
     */
    private function deleteViaOrderItemIds(string $table, string $column, array $orderIds): int
    {
        $itemIds = $this->orderItemIdsForOrders($orderIds);
        if ($itemIds === []) {
            return 0;
        }

        return $this->deleteWhereInChunked($table, $column, $itemIds);
    }

    /**
     * @param  array{parent?: string, parent_fk?: string, parent_order_col?: string}  $plan
     * @param  list<string>  $orderIds
     */
    private function countViaParent(string $table, array $plan, array $orderIds): int
    {
        $parent = $plan['parent'] ?? '';
        $fk = $plan['parent_fk'] ?? '';
        $parentOrderCol = $plan['parent_order_col'] ?? 'order_id';

        $parentIds = DB::table($parent)->whereIn($parentOrderCol, $orderIds)->pluck('id');
        if ($parentIds->isEmpty()) {
            return 0;
        }

        return (int) DB::table($table)->whereIn($fk, $parentIds->all())->count();
    }

    /**
     * @param  array{parent?: string, parent_fk?: string, parent_order_col?: string}  $plan
     * @param  list<string>  $orderIds
     */
    private function deleteViaParent(string $table, array $plan, array $orderIds): int
    {
        $parent = $plan['parent'] ?? '';
        $fk = $plan['parent_fk'] ?? '';
        $parentOrderCol = $plan['parent_order_col'] ?? 'order_id';

        $parentIds = DB::table($parent)->whereIn($parentOrderCol, $orderIds)->pluck('id')->map(fn ($id) => (string) $id)->all();
        if ($parentIds === []) {
            return 0;
        }

        return $this->deleteWhereInChunked($table, $fk, $parentIds);
    }

    /**
     * @param  array{morph_type?: string, morph_id?: string}  $plan
     * @param  list<string>  $orderIds
     */
    private function deleteMorph(string $table, array $plan, array $orderIds): int
    {
        $typeCol = $plan['morph_type'] ?? '';
        $idCol = $plan['morph_id'] ?? '';
        if ($typeCol === '' || $idCol === '' || ! Schema::hasColumn($table, $typeCol) || ! Schema::hasColumn($table, $idCol)) {
            return 0;
        }

        $total = 0;
        foreach (array_chunk($orderIds, self::CHUNK_SIZE) as $chunk) {
            $total += (int) DB::table($table)
                ->where($typeCol, Order::class)
                ->whereIn($idCol, $chunk)
                ->delete();
        }

        return $total;
    }

    /**
     * @param  array{morph_type?: string, morph_id?: string}  $plan
     * @param  list<string>  $orderIds
     */
    private function countMorphReference(string $table, array $plan, array $orderIds): int
    {
        $typeCol = $plan['morph_type'] ?? 'reference_type';
        $idCol = $plan['morph_id'] ?? 'reference_id';
        if (! Schema::hasColumn($table, $typeCol) || ! Schema::hasColumn($table, $idCol)) {
            return 0;
        }

        $itemIds = Schema::hasTable('order_items')
            ? DB::table('order_items')->whereIn('order_id', $orderIds)->pluck('id')->all()
            : [];

        return (int) DB::table($table)
            ->where(function ($q) use ($typeCol, $idCol, $orderIds, $itemIds): void {
                $q->where(function ($inner) use ($typeCol, $idCol, $orderIds): void {
                    $inner->where($typeCol, Order::class)->whereIn($idCol, $orderIds);
                });
                if ($itemIds !== []) {
                    $q->orWhere(function ($inner) use ($typeCol, $idCol, $itemIds): void {
                        $inner->where($typeCol, OrderItem::class)->whereIn($idCol, $itemIds);
                    });
                }
            })
            ->count();
    }

    /**
     * @param  array{morph_type?: string, morph_id?: string}  $plan
     * @param  list<string>  $orderIds
     */
    private function deleteMorphReference(string $table, array $plan, array $orderIds): int
    {
        $typeCol = $plan['morph_type'] ?? 'reference_type';
        $idCol = $plan['morph_id'] ?? 'reference_id';
        if (! Schema::hasColumn($table, $typeCol) || ! Schema::hasColumn($table, $idCol)) {
            return 0;
        }

        $itemIds = Schema::hasTable('order_items')
            ? DB::table('order_items')->whereIn('order_id', $orderIds)->pluck('id')->map(fn ($id) => (string) $id)->all()
            : [];

        $total = 0;
        foreach (array_chunk($orderIds, self::CHUNK_SIZE) as $chunk) {
            $total += (int) DB::table($table)
                ->where($typeCol, Order::class)
                ->whereIn($idCol, $chunk)
                ->delete();
        }
        foreach (array_chunk($itemIds, self::CHUNK_SIZE) as $chunk) {
            $total += (int) DB::table($table)
                ->where($typeCol, OrderItem::class)
                ->whereIn($idCol, $chunk)
                ->delete();
        }

        return $total;
    }

    /**
     * @param  list<string>  $orderIds
     */
    private function countJsonOrderId(string $table, string $jsonPath, array $orderIds): int
    {
        if (! Schema::hasColumn($table, 'data')) {
            return 0;
        }

        $total = 0;
        foreach ($orderIds as $orderId) {
            $total += (int) DB::table($table)->where($jsonPath, $orderId)->count();
        }

        return $total;
    }

    /**
     * @param  list<string>  $orderIds
     */
    private function deleteJsonOrderId(string $table, string $jsonPath, array $orderIds): int
    {
        if (! Schema::hasColumn($table, 'data')) {
            return 0;
        }

        $total = 0;
        foreach ($orderIds as $orderId) {
            $total += (int) DB::table($table)->where($jsonPath, $orderId)->delete();
        }

        return $total;
    }

    /**
     * @param  list<string>  $orderIds
     */
    private function hardDeleteOrders(array $orderIds): int
    {
        return $this->deleteWhereInChunked('orders', 'id', $orderIds);
    }

    /**
     * @param  list<string>  $ids
     */
    private function deleteWhereInChunked(string $table, string $column, array $ids): int
    {
        if ($ids === [] || ! Schema::hasTable($table)) {
            return 0;
        }

        if (! Schema::hasColumn($table, $column)) {
            throw new RuntimeException(
                "Abandoned-order cleanup aborted: refuse whole-table fallback — [{$table}.{$column}] missing.",
            );
        }

        $total = 0;
        foreach (array_chunk($ids, self::CHUNK_SIZE) as $chunk) {
            $total += (int) DB::table($table)->whereIn($column, $chunk)->delete();
        }

        return $total;
    }

    /**
     * @return array{variant_rows: int, inventory_rows: int, on_hand_sum: int, reserved_sum: int, simple_qty_sum: int, simple_reserved_sum: int}
     */
    private function inventoryIntegritySnapshot(): array
    {
        $variantRows = 0;
        $onHand = 0;
        $reserved = 0;
        if (Schema::hasTable('variant_inventories')) {
            $variantRows = (int) DB::table('variant_inventories')->count();
            $onHand = (int) DB::table('variant_inventories')->sum('on_hand');
            $reserved = (int) DB::table('variant_inventories')->sum('reserved');
        }

        $invRows = 0;
        $simpleQty = 0;
        $simpleReserved = 0;
        if (Schema::hasTable('inventory')) {
            $invRows = (int) DB::table('inventory')->count();
            if (Schema::hasColumn('inventory', 'quantity')) {
                $simpleQty = (int) DB::table('inventory')->sum('quantity');
            }
            if (Schema::hasColumn('inventory', 'reserved_quantity')) {
                $simpleReserved = (int) DB::table('inventory')->sum('reserved_quantity');
            }
        }

        return [
            'variant_rows' => $variantRows,
            'inventory_rows' => $invRows,
            'on_hand_sum' => $onHand,
            'reserved_sum' => $reserved,
            'simple_qty_sum' => $simpleQty,
            'simple_reserved_sum' => $simpleReserved,
        ];
    }

    /**
     * @param  array{variant_rows: int, inventory_rows: int, on_hand_sum: int, reserved_sum: int, simple_qty_sum: int, simple_reserved_sum: int}  $inventory
     * @return array<string, int>
     */
    private function preserveReport(array $inventory): array
    {
        $counts = [];
        foreach (AbandonedOrderCleanupManifest::PRESERVE_CHECKS as $label => $table) {
            $counts[$label] = Schema::hasTable($table) ? (int) DB::table($table)->count() : 0;
        }

        $counts['paid_orders'] = Schema::hasTable('orders')
            ? (int) DB::table('orders')->whereNull('deleted_at')->where('status', OrderStatus::Paid->value)->count()
            : 0;
        $counts['successful_transactions'] = Schema::hasTable('payment_transactions')
            ? (int) DB::table('payment_transactions')
                ->where('status', PaymentTransactionStatus::Successful->value)
                ->count()
            : 0;
        $counts['inventory_on_hand_sum'] = $inventory['on_hand_sum'];
        $counts['inventory_reserved_sum'] = $inventory['reserved_sum'];
        $counts['inventory_simple_qty_sum'] = $inventory['simple_qty_sum'];
        $counts['inventory_simple_reserved_sum'] = $inventory['simple_reserved_sum'];

        return $counts;
    }

    /**
     * Catalog/identity drift ignores reserved sums (release may change reserved intentionally).
     *
     * @param  array<string, int>  $before
     * @param  array<string, int>  $after
     * @return array<string, array{before: int, after: int}>
     */
    private function detectCatalogDrift(array $before, array $after): array
    {
        $keys = [
            'users',
            'customer_profiles',
            'products',
            'product_variants',
            'inventory',
            'variant_inventories',
            'china_commercial_stocks',
            'admins',
            'payment_methods',
            'coupons',
            'promotions',
            'paid_orders',
            'successful_transactions',
            'inventory_on_hand_sum',
            'inventory_simple_qty_sum',
        ];

        $drift = [];
        foreach ($keys as $key) {
            $b = (int) ($before[$key] ?? 0);
            $a = (int) ($after[$key] ?? 0);
            if ($b !== $a) {
                $drift[$key] = ['before' => $b, 'after' => $a];
            }
        }

        return $drift;
    }
}

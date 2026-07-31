<?php

namespace App\Services\Commerce;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Development-only cleanup for transactional commerce records.
 *
 * Preserves catalog, identity, settings, and master/reference data.
 */
class ResetCommerceDataService
{
    /**
     * Child tables first — respects FK constraints without disabling checks.
     *
     * @var list<string>
     */
    private const DELETION_ORDER = [
        'refund_transactions',
        'return_items',
        'return_requests',
        'refunds',
        'customer_agent_pickup_histories',
        'customer_agent_pickups',
        'china_workflow_histories',
        'china_workflow_records',
        'pos_sale_idempotency_keys',
        'pos_receipts',
        'promotion_usages',
        'order_discount_snapshots',
        'coupon_usages',
        'order_cost_snapshots',
        'profit_records',
        'payment_transactions',
        'payments',
        'shipment_tracking_events',
        'fulfillment_status_histories',
        'shipment_status_histories',
        'order_items',
        'shipping_addresses',
        'order_status_history',
        'order_tracking_events',
        'warehouse_jobs',
        'delivery_options',
        'shipments',
        'fulfillments',
        'orders',
        'china_order_status_history',
        'china_order_quote_items',
        'china_order_quotes',
        'china_order_attachments',
        'china_order_source_links',
        'china_order_items',
        'china_order_requests',
        'checkout_sessions',
        'cart_items',
        'carts',
    ];

    /**
     * Tables that may still contain commerce-adjacent rows after reset.
     *
     * @var list<string>
     */
    private const REMAINING_TRANSACTION_TABLES = [
        'purchase_orders',
        'purchase_order_items',
        'receiving_records',
        'receiving_record_items',
        'pos_sessions',
        'reviews',
        'loyalty_ledger_entries',
        'loyalty_redemptions',
        'activity_logs',
    ];

    /**
     * @return array{
     *     deleted: array<string, int>,
     *     remaining: array<string, int>
     * }
     */
    public function handle(): array
    {
        $deletedByTable = [];

        DB::transaction(function () use (&$deletedByTable): void {
            foreach (self::DELETION_ORDER as $table) {
                $deletedByTable[$table] = $this->deleteTable($table);
            }
        });

        return [
            'deleted' => $this->summarizeDeleted($deletedByTable),
            'remaining' => $this->scanRemainingTransactionTables(),
        ];
    }

    /**
     * @param  array<string, int>  $deletedByTable
     * @return array<string, int>
     */
    private function summarizeDeleted(array $deletedByTable): array
    {
        return [
            'orders' => $deletedByTable['orders'] ?? 0,
            'order_items' => $deletedByTable['order_items'] ?? 0,
            'payments' => $deletedByTable['payments'] ?? 0,
            'payment_transactions' => $deletedByTable['payment_transactions'] ?? 0,
            'shipments' => ($deletedByTable['shipments'] ?? 0)
                + ($deletedByTable['shipment_tracking_events'] ?? 0)
                + ($deletedByTable['shipment_status_histories'] ?? 0),
            'fulfillment_records' => ($deletedByTable['fulfillments'] ?? 0)
                + ($deletedByTable['warehouse_jobs'] ?? 0)
                + ($deletedByTable['delivery_options'] ?? 0)
                + ($deletedByTable['customer_agent_pickups'] ?? 0)
                + ($deletedByTable['customer_agent_pickup_histories'] ?? 0)
                + ($deletedByTable['china_workflow_records'] ?? 0)
                + ($deletedByTable['china_workflow_histories'] ?? 0),
            'cart_records' => ($deletedByTable['carts'] ?? 0)
                + ($deletedByTable['cart_items'] ?? 0)
                + ($deletedByTable['checkout_sessions'] ?? 0),
            'returns_and_refunds' => ($deletedByTable['return_requests'] ?? 0)
                + ($deletedByTable['return_items'] ?? 0)
                + ($deletedByTable['refunds'] ?? 0)
                + ($deletedByTable['refund_transactions'] ?? 0),
            'china_order_requests' => ($deletedByTable['china_order_requests'] ?? 0)
                + ($deletedByTable['china_order_items'] ?? 0)
                + ($deletedByTable['china_order_quotes'] ?? 0)
                + ($deletedByTable['china_order_quote_items'] ?? 0)
                + ($deletedByTable['china_order_attachments'] ?? 0)
                + ($deletedByTable['china_order_source_links'] ?? 0)
                + ($deletedByTable['china_order_status_history'] ?? 0),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function scanRemainingTransactionTables(): array
    {
        $remaining = [];

        foreach (self::REMAINING_TRANSACTION_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $count = (int) DB::table($table)->count();
            if ($count > 0) {
                $remaining[$table] = $count;
            }
        }

        return $remaining;
    }

    private function deleteTable(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)->delete();
    }
}

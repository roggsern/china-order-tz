<?php

namespace App\Services\China\Procurement;

use App\Enums\CommerceChannelCode;
use App\Enums\OrderStatus;
use App\Models\ChinaProcurementRequirement;
use App\Models\ChinaProcurementRequirementLink;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Commerce\CommerceChannelResolver;
use App\Services\ConfigurationHealth\Concerns\BuildsHealthCheckResult;

/**
 * Read-only reconciliation between paid China-import demand and procurement board rows.
 */
final class ChinaProcurementReconciliationService
{
    use BuildsHealthCheckResult;

    public function __construct(
        private readonly CommerceChannelResolver $channels,
    ) {}

    /**
     * @return array{
     *   overall_score: int,
     *   status: string,
     *   checks: list<array{group: string, status: string, message: string, severity: string, samples?: list<array<string, mixed>>}>,
     *   summary: array{critical_count: int, warning_count: int, info_count: int, healthy_count: int}
     * }
     */
    public function report(): array
    {
        $checks = [
            $this->checkMissingRequirementLinks(),
            $this->checkQuantityMismatch(),
            $this->checkOrphanLinks(),
        ];

        $critical = 0;
        $warning = 0;
        $info = 0;
        $healthy = 0;

        foreach ($checks as $check) {
            match ($check['status']) {
                'critical' => $critical++,
                'warning' => $warning++,
                'healthy' => $healthy++,
                default => $info++,
            };
        }

        $score = max(0, 100 - ($critical * 25) - ($warning * 10) - ($info * 2));
        $status = $critical > 0 ? 'critical' : ($warning > 0 ? 'warning' : 'healthy');

        return [
            'overall_score' => $score,
            'status' => $status,
            'checks' => $checks,
            'summary' => [
                'critical_count' => $critical,
                'warning_count' => $warning,
                'info_count' => $info,
                'healthy_count' => $healthy,
            ],
        ];
    }

    /**
     * @return array{group: string, status: string, message: string, severity: string, samples?: list<array<string, mixed>>}
     */
    private function checkMissingRequirementLinks(): array
    {
        $missing = [];

        foreach ($this->paidChinaImportOrderItems() as $item) {
            $hasLink = ChinaProcurementRequirementLink::query()
                ->where('order_item_id', $item->id)
                ->exists();

            if (! $hasLink) {
                $missing[] = [
                    'order_id' => $item->order_id,
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'quantity' => (int) $item->quantity,
                ];
            }
        }

        if ($missing === []) {
            return $this->result('missing_requirement_links', 'healthy', 'All paid China-import order items have procurement links.');
        }

        return array_merge(
            $this->result(
                'missing_requirement_links',
                'critical',
                sprintf('%d paid China-import order item(s) missing procurement requirement links.', count($missing)),
            ),
            ['samples' => array_slice($missing, 0, 25)],
        );
    }

    /**
     * @return array{group: string, status: string, message: string, severity: string, samples?: list<array<string, mixed>>}
     */
    private function checkQuantityMismatch(): array
    {
        $mismatches = ChinaProcurementRequirement::query()
            ->select('china_procurement_requirements.*')
            ->selectRaw('COALESCE(SUM(china_procurement_requirement_links.quantity), 0) as linked_quantity')
            ->leftJoin(
                'china_procurement_requirement_links',
                'china_procurement_requirement_links.requirement_id',
                '=',
                'china_procurement_requirements.id',
            )
            ->groupBy('china_procurement_requirements.id')
            ->havingRaw('china_procurement_requirements.quantity_required != COALESCE(SUM(china_procurement_requirement_links.quantity), 0)')
            ->limit(25)
            ->get()
            ->map(fn (ChinaProcurementRequirement $row) => [
                'requirement_id' => $row->id,
                'product_id' => $row->product_id,
                'product_variant_id' => $row->product_variant_id,
                'quantity_required' => (int) $row->quantity_required,
                'linked_quantity' => (int) $row->linked_quantity,
            ])
            ->all();

        if ($mismatches === []) {
            return $this->result('quantity_mismatch', 'healthy', 'Procurement requirement quantities match linked order demand.');
        }

        return array_merge(
            $this->result(
                'quantity_mismatch',
                'warning',
                sprintf('%d procurement requirement(s) have quantity_required mismatched to linked demand.', count($mismatches)),
            ),
            ['samples' => $mismatches],
        );
    }

    /**
     * @return array{group: string, status: string, message: string, severity: string, samples?: list<array<string, mixed>>}
     */
    private function checkOrphanLinks(): array
    {
        $orphans = ChinaProcurementRequirementLink::query()
            ->with(['order:id,status,paid_at', 'orderItem:id'])
            ->limit(100)
            ->get()
            ->filter(function (ChinaProcurementRequirementLink $link): bool {
                if ($link->orderItem === null) {
                    return true;
                }

                $order = $link->order;
                if ($order === null) {
                    return true;
                }

                if ($order->paid_at === null) {
                    return true;
                }

                $status = $order->status instanceof OrderStatus
                    ? $order->status
                    : OrderStatus::tryFrom((string) $order->status);

                return in_array($status, [
                    OrderStatus::Cancelled,
                    OrderStatus::RefundPending,
                    OrderStatus::Refunded,
                ], true);
            })
            ->take(25)
            ->map(fn (ChinaProcurementRequirementLink $link) => [
                'link_id' => $link->id,
                'requirement_id' => $link->requirement_id,
                'order_id' => $link->order_id,
                'order_item_id' => $link->order_item_id,
                'order_status' => $link->order?->status instanceof OrderStatus
                    ? $link->order->status->value
                    : (string) $link->order?->status,
            ])
            ->values()
            ->all();

        if ($orphans === []) {
            return $this->result('orphan_links', 'healthy', 'No orphan procurement requirement links detected.');
        }

        return array_merge(
            $this->result(
                'orphan_links',
                'warning',
                sprintf('%d procurement link(s) reference cancelled/unpaid/missing orders.', count($orphans)),
            ),
            ['samples' => $orphans],
        );
    }

    /**
     * @return list<OrderItem>
     */
    private function paidChinaImportOrderItems(): array
    {
        $items = [];

        Order::query()
            ->whereNotNull('paid_at')
            ->whereNotIn('status', [
                OrderStatus::Cancelled->value,
                OrderStatus::Refunded->value,
            ])
            ->with(['items.product.commerceChannel'])
            ->orderByDesc('paid_at')
            ->chunkById(100, function ($orders) use (&$items): void {
                foreach ($orders as $order) {
                    if (! $this->channels->isChinaImportOrder($order)) {
                        continue;
                    }

                    foreach ($order->items as $item) {
                        $item->loadMissing('product.commerceChannel');
                        $product = $item->product;
                        if ($product === null) {
                            continue;
                        }

                        $channel = $this->channels->resolveProductChannel($product);
                        if (CommerceChannelCode::tryFrom($channel->code) !== CommerceChannelCode::ChinaImport) {
                            continue;
                        }

                        $items[] = $item;
                    }
                }
            });

        return $items;
    }
}

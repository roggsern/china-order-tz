<?php

namespace App\Services\CostProfit;

use App\Models\Order;
use App\Models\OrderCostSnapshot;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductShippingOption;
use App\Models\SupplierProduct;
use App\Services\Procurement\SupplierCostService;

/**
 * Resolves current cost inputs into snapshot payloads (does not persist).
 */
class CostSnapshotService
{
    public function __construct(
        private readonly SupplierCostService $supplierCosts,
    ) {}

    /**
     * @return array{
     *     supplier_cost: string,
     *     shipping_cost: string,
     *     other_cost: string,
     *     total_cost: string,
     *     currency: string,
     *     exchange_rate: string
     * }
     */
    public function resolveForOrderItem(OrderItem $item, ?float $otherCost = null): array
    {
        $item->loadMissing(['product.supplier', 'variant', 'order']);

        $qty = max(1, (int) $item->quantity);
        $currency = strtoupper((string) (
            $item->currency_snapshot
            ?: $item->currency
            ?: $item->order?->currency
            ?: 'TZS'
        ));

        $unitSupplier = $this->resolveUnitSupplierCost($item, $currency);
        $unitShipping = $this->resolveUnitShippingCost($item, $currency);
        $other = $otherCost !== null
            ? max(0, $otherCost)
            : 0.0;

        $supplierTotal = round($unitSupplier * $qty, 2);
        $shippingTotal = round($unitShipping * $qty, 2);
        $total = round($supplierTotal + $shippingTotal + $other, 2);

        return [
            'supplier_cost' => number_format($supplierTotal, 2, '.', ''),
            'shipping_cost' => number_format($shippingTotal, 2, '.', ''),
            'other_cost' => number_format($other, 2, '.', ''),
            'total_cost' => number_format($total, 2, '.', ''),
            'currency' => $currency,
            'exchange_rate' => $this->exchangeRateSnapshot($currency),
        ];
    }

    public function exchangeRateSnapshot(string $currency): string
    {
        $currency = strtoupper($currency);
        $rates = config('cost_profit.exchange_rates', []);
        $rate = $rates[$currency] ?? ($currency === 'TZS' ? 1 : config('cost_profit.default_exchange_rate', 1));

        return number_format((float) $rate, 8, '.', '');
    }

    private function resolveUnitSupplierCost(OrderItem $item, string $currency): float
    {
        $variantId = $item->product_variant_id;
        if (! $variantId) {
            return 0.0;
        }

        /** @var Product|null $product */
        $product = $item->product;
        $supplierId = $product?->supplier_id;

        $history = $this->supplierCosts->latestForVariant($variantId, $supplierId);
        if ($history !== null) {
            return (float) $history->purchase_cost;
        }

        $mapping = SupplierProduct::query()
            ->where('product_variant_id', $variantId)
            ->where('is_active', true)
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->orderByDesc('updated_at')
            ->first();

        if ($mapping !== null) {
            return (float) $mapping->purchase_cost;
        }

        // Last resort: product cost_price if present.
        if ($product?->cost_price !== null) {
            return (float) $product->cost_price;
        }

        return 0.0;
    }

    private function resolveUnitShippingCost(OrderItem $item, string $currency): float
    {
        // Prefer catalog ProductShippingOption price for the snapshotted mode.
        $mode = strtolower((string) ($item->shipping_mode_snapshot ?: $item->shipping_method ?: ''));
        $mode = match ($mode) {
            'air_freight', 'air' => 'air',
            'sea_freight', 'sea' => 'sea',
            default => $mode,
        };

        if ($item->product_id && in_array($mode, ['air', 'sea'], true)) {
            $option = ProductShippingOption::query()
                ->where('product_id', $item->product_id)
                ->where('transport_mode', $mode)
                ->where('is_available', true)
                ->orderBy('sort_order')
                ->first();

            if ($option !== null) {
                return (float) $option->price;
            }
        }

        // Fallback: customer-facing shipping on the line (already snapshotted commercially).
        if ($item->shipping_price_snapshot !== null) {
            return (float) $item->shipping_price_snapshot;
        }

        if ($item->shipping_price !== null) {
            return (float) $item->shipping_price;
        }

        return 0.0;
    }
}

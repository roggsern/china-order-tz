<?php

namespace App\Services\Pos;

use App\Enums\InventoryDisposition;
use App\Enums\PosReturnType;
use App\Enums\RefundTransactionStatus;
use App\Enums\ReturnItemResolution;
use App\Enums\ReturnRequestStatus;
use App\Enums\SalesOrigin;
use App\Events\Audit\StorePlatformAudit;
use App\Events\Returns\RefundCompleted;
use App\Events\Returns\RefundCreated;
use App\Events\Returns\ReturnCompleted;
use App\Events\Returns\ReturnRequested;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosReceipt;
use App\Models\PosSession;
use App\Models\ProductVariant;
use App\Models\RefundTransaction;
use App\Models\ReturnItem;
use App\Models\ReturnReason;
use App\Models\ReturnRequest;
use App\Models\VariantInventory;
use App\Services\CostProfit\ProfitEngine;
use App\Services\Inventory\InventoryControlEngine;
use App\Services\Stores\ActiveStoreContext;
use App\Services\Stores\StoreService;
use App\Support\Pos\PosErrors;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * POS Returns / Exchanges / Refunds — thin orchestration over Return + Refund + Inventory + Profit engines.
 * Does not create a second order or payment system.
 */
class PosReturnService
{
    public function __construct(
        private readonly ActiveStoreContext $storeContext,
        private readonly StoreService $stores,
        private readonly PosReturnEligibilityService $eligibility,
        private readonly PosReceiptService $receipts,
        private readonly PosSessionService $sessions,
        private readonly ProfitEngine $profits,
        private readonly PosSessionCashService $sessionCash,
        private readonly InventoryControlEngine $inventoryControl,
    ) {}

    /**
     * @param  array{q?: string|null, receipt_number?: string|null, order_number?: string|null, store_id?: string|null, from?: string|null, to?: string|null}  $filters
     * @return list<array<string, mixed>>
     */
    public function searchOrders(Admin $cashier, array $filters = []): array
    {
        $paginator = $this->receipts->search($cashier, $filters);
        $rows = [];

        foreach ($paginator->getCollection() as $receipt) {
            /** @var PosReceipt $receipt */
            $order = $receipt->order;
            if ($order === null) {
                continue;
            }
            $eval = $this->eligibility->evaluate($order, $cashier);
            $rows[] = [
                'receipt' => $receipt,
                'order' => $order->loadMissing(['items', 'user', 'store']),
                'eligible' => $eval['eligible'],
                'reason' => $eval['reason'],
                'returnable_items' => array_map(fn (array $row) => [
                    'order_item_id' => $row['order_item']->id,
                    'product_name' => $row['order_item']->product_name ?? $row['order_item']->product_name_snapshot,
                    'variant_name' => $row['order_item']->variant_name ?? $row['order_item']->variant_name_snapshot,
                    'sku' => $row['order_item']->sku ?? $row['order_item']->sku_snapshot,
                    'product_variant_id' => $row['order_item']->product_variant_id,
                    'unit_price' => number_format((float) $row['order_item']->unit_price, 2, '.', ''),
                    'purchased_quantity' => (int) $row['order_item']->quantity,
                    'remaining_quantity' => $row['remaining_quantity'],
                ], $this->eligibility->returnableItems($order)),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function lookupOrder(Admin $cashier, Order $order): array
    {
        $this->storeContext->assertCanAccess($cashier, $order->store()->firstOrFail());
        $eval = $this->eligibility->evaluate($order, $cashier);
        $receipt = PosReceipt::query()->where('order_id', $order->id)->first();

        return [
            'order' => $order->load(['items', 'user', 'store', 'payments']),
            'receipt' => $receipt,
            'eligible' => $eval['eligible'],
            'reason' => $eval['reason'],
            'returnable_items' => array_map(fn (array $row) => [
                'order_item_id' => $row['order_item']->id,
                'product_name' => $row['order_item']->product_name ?? $row['order_item']->product_name_snapshot,
                'variant_name' => $row['order_item']->variant_name ?? $row['order_item']->variant_name_snapshot,
                'sku' => $row['order_item']->sku ?? $row['order_item']->sku_snapshot,
                'product_id' => $row['order_item']->product_id,
                'product_variant_id' => $row['order_item']->product_variant_id,
                'unit_price' => number_format((float) $row['order_item']->unit_price, 2, '.', ''),
                'purchased_quantity' => (int) $row['order_item']->quantity,
                'remaining_quantity' => $row['remaining_quantity'],
            ], $this->eligibility->returnableItems($order)),
        ];
    }

    /**
     * Complete a POS return or exchange in one transactional path.
     *
     * @param  array{
     *   order_id: string,
     *   return_type: string,
     *   return_reason_id?: string|null,
     *   reason?: string|null,
     *   notes?: string|null,
     *   refund_method?: string|null,
     *   refund_reference?: string|null,
     *   items: list<array{
     *     order_item_id: string,
     *     quantity: int,
     *     inventory_disposition?: string|null,
     *     exchange_variant_id?: string|null
     *   }>
     * }  $input
     * @return array{return: ReturnRequest, refund: RefundTransaction|null}
     */
    public function process(Admin $cashier, array $input): array
    {
        $order = Order::query()->with(['items', 'store', 'user', 'payments'])->findOrFail($input['order_id']);
        $this->eligibility->assertEligible($order, $cashier);

        $session = $this->sessions->currentOpen($cashier);
        if ($session === null || $session->store_id !== $order->store_id) {
            PosErrors::sessionRequired();
        }

        $type = PosReturnType::tryFrom((string) ($input['return_type'] ?? ''))
            ?? throw ValidationException::withMessages(['return_type' => ['Invalid return type.']]);

        $reasonModel = null;
        if (! empty($input['return_reason_id'])) {
            $reasonModel = ReturnReason::query()->where('is_active', true)->findOrFail($input['return_reason_id']);
        }

        $reasonLabel = $reasonModel?->name ?? trim((string) ($input['reason'] ?? ''));
        if ($reasonLabel === '') {
            throw ValidationException::withMessages([
                'return_reason_id' => ['A return reason is required.'],
            ]);
        }

        if ($type === PosReturnType::Refund && empty($input['refund_method'])) {
            throw ValidationException::withMessages([
                'refund_method' => ['Refund method is required for refund returns.'],
            ]);
        }

        $prepared = $this->prepareItems($order, $type, $input['items'] ?? []);

        return DB::transaction(function () use (
            $cashier,
            $order,
            $session,
            $type,
            $reasonModel,
            $reasonLabel,
            $input,
            $prepared,
        ) {
            event(StorePlatformAudit::returnStarted($order, $cashier));

            $refundTotal = '0.00';
            foreach ($prepared as $row) {
                $refundTotal = bcadd($refundTotal, $row['refund_amount'], 2);
            }

            $receipt = PosReceipt::query()->where('order_id', $order->id)->first();

            $return = ReturnRequest::query()->create([
                'return_number' => $this->nextReturnNumber($order->store?->code ?? 'POS'),
                'order_id' => $order->id,
                'customer_id' => $order->user_id,
                'sales_origin' => SalesOrigin::Pos->value,
                'return_type' => $type->value,
                'store_id' => $order->store_id,
                'pos_session_id' => $session->id,
                'processed_by' => $cashier->id,
                'return_reason_id' => $reasonModel?->id,
                'original_receipt_id' => $receipt?->id,
                'refund_method' => $type === PosReturnType::Refund
                    ? strtoupper((string) $input['refund_method'])
                    : ($input['refund_method'] ?? null),
                'refund_total' => $refundTotal,
                'status' => ReturnRequestStatus::Completed,
                'reason' => $reasonLabel,
                'description' => $input['notes'] ?? null,
                'admin_notes' => $input['notes'] ?? null,
                'approved_by' => $cashier->id,
                'approved_at' => now(),
                'completed_at' => now(),
            ]);

            foreach ($prepared as $row) {
                ReturnItem::query()->create([
                    'return_request_id' => $return->id,
                    'order_item_id' => $row['order_item']->id,
                    'quantity' => $row['quantity'],
                    'reason' => $reasonLabel,
                    'condition' => $row['disposition']->value,
                    'inventory_disposition' => $row['disposition']->value,
                    'resolution' => $row['resolution']->value,
                    'refund_amount' => $row['refund_amount'],
                    'replacement_requested' => $type === PosReturnType::Exchange,
                    'exchange_variant_id' => $row['exchange_variant']?->id,
                    'exchange_unit_price' => $row['exchange_unit_price'],
                ]);

                $this->applyInventory(
                    $order,
                    $row['order_item'],
                    $row['quantity'],
                    $row['disposition'],
                    $row['exchange_variant'],
                    $cashier,
                );
            }

            $refund = null;
            if ($type === PosReturnType::Refund && bccomp($refundTotal, '0.00', 2) > 0) {
                $refund = RefundTransaction::query()->create([
                    'return_request_id' => $return->id,
                    'order_id' => $order->id,
                    'amount' => $refundTotal,
                    'currency' => $order->currency ?? 'TZS',
                    'status' => RefundTransactionStatus::Completed,
                    'method' => strtoupper((string) $input['refund_method']),
                    'reference' => $input['refund_reference'] ?? null,
                    'notes' => $input['notes'] ?? 'POS refund',
                ]);
                event(new RefundCreated($refund, $cashier));
                event(new RefundCompleted($refund->fresh(), $cashier));
                event(StorePlatformAudit::refundIssued($refund, $cashier));
            }

            $this->profits->reverseForReturn($order, $refundTotal, $cashier);

            $return->forceFill([
                'receipt_snapshot' => $this->buildReceiptSnapshot($return->fresh([
                    'items.orderItem',
                    'items.exchangeVariant',
                    'returnReason',
                    'store',
                    'processor',
                    'originalReceipt',
                    'order.user',
                ]), $refund),
            ])->save();

            event(new ReturnRequested($return->fresh(['customer', 'order'])));
            event(new ReturnCompleted($return->fresh(['customer', 'order', 'items']), $cashier));
            event(StorePlatformAudit::returnCompleted($return->fresh(), $cashier));

            if ($type === PosReturnType::Exchange) {
                event(StorePlatformAudit::exchangeCompleted($return->fresh(), $cashier));
            }

            $this->sessionCash->persistRunningTotals($session->fresh() ?? $session);

            Log::info('pos.return_complete', [
                'return_id' => $return->id,
                'order_id' => $order->id,
                'session_id' => $session->id,
                'admin_id' => $cashier->id,
                'return_type' => $type->value,
                'refund_total' => $refundTotal,
                'refund_method' => $return->refund_method,
            ]);

            if ($refund !== null) {
                Log::info('pos.refund_complete', [
                    'refund_id' => $refund->id,
                    'return_id' => $return->id,
                    'amount' => $refund->amount,
                    'method' => $refund->method,
                ]);
            }

            return [
                'return' => $return->fresh([
                    'items.orderItem',
                    'items.exchangeVariant',
                    'returnReason',
                    'store',
                    'processor',
                    'originalReceipt',
                    'order.user',
                    'latestRefund',
                ]),
                'refund' => $refund?->fresh(),
            ];
        });
    }

    public function show(Admin $cashier, ReturnRequest $return): ReturnRequest
    {
        if ($return->store_id) {
            $this->storeContext->assertCanAccess($cashier, $return->store()->firstOrFail());
        }

        return $return->load([
            'items.orderItem',
            'items.exchangeVariant',
            'returnReason',
            'store',
            'processor',
            'originalReceipt',
            'order.user',
            'latestRefund',
            'refundTransactions',
        ]);
    }

    /**
     * @param  array{store_id?: string|null, from?: string|null, to?: string|null, q?: string|null, per_page?: int}  $filters
     * @return LengthAwarePaginator<int, ReturnRequest>
     */
    public function list(Admin $cashier, array $filters = []): LengthAwarePaginator
    {
        $query = ReturnRequest::query()
            ->with(['store', 'processor', 'order', 'returnReason', 'latestRefund'])
            ->where('sales_origin', SalesOrigin::Pos->value)
            ->latest('completed_at');

        if (! $cashier->is_super_admin) {
            $storeIds = $this->storeContext->assignedStores($cashier)->pluck('id');
            $query->whereIn('store_id', $storeIds);
        }

        if (! empty($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('completed_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('completed_at', '<=', $filters['to']);
        }

        if (! empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function (Builder $b) use ($q) {
                $b->where('return_number', 'like', "%{$q}%")
                    ->orWhereHas('order', fn (Builder $o) => $o->where('order_number', 'like', "%{$q}%"))
                    ->orWhereHas('originalReceipt', fn (Builder $r) => $r->where('receipt_number', 'like', "%{$q}%"));
            });
        }

        return $query->paginate(min(100, max(1, (int) ($filters['per_page'] ?? 25))));
    }

    /**
     * @return array<string, mixed>
     */
    public function report(Admin $cashier, array $filters = []): array
    {
        $base = ReturnRequest::query()->where('sales_origin', SalesOrigin::Pos->value);
        if (! $cashier->is_super_admin) {
            $base->whereIn('store_id', $this->storeContext->assignedStores($cashier)->pluck('id'));
        }
        if (! empty($filters['store_id'])) {
            $base->where('store_id', $filters['store_id']);
        }
        if (! empty($filters['from'])) {
            $base->whereDate('completed_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $base->whereDate('completed_at', '<=', $filters['to']);
        }

        $returns = (clone $base)->count();
        $exchanges = (clone $base)->where('return_type', PosReturnType::Exchange->value)->count();
        $refundAmount = (string) ((clone $base)->sum('refund_total') ?? 0);
        $damaged = ReturnItem::query()
            ->where('inventory_disposition', InventoryDisposition::Damaged->value)
            ->whereHas('returnRequest', fn (Builder $q) => $q->whereIn('id', (clone $base)->select('id')))
            ->count();

        $byMethod = RefundTransaction::query()
            ->where('status', RefundTransactionStatus::Completed)
            ->whereIn('return_request_id', (clone $base)->select('id'))
            ->selectRaw('method, COUNT(*) as count, COALESCE(SUM(amount),0) as amount')
            ->groupBy('method')
            ->get()
            ->map(fn ($r) => [
                'method' => $r->method,
                'count' => (int) $r->count,
                'amount' => number_format((float) $r->amount, 2, '.', ''),
            ])
            ->all();

        return [
            'returns_count' => $returns,
            'exchange_count' => $exchanges,
            'refund_amount' => number_format((float) $refundAmount, 2, '.', ''),
            'damaged_item_lines' => $damaged,
            'by_refund_method' => $byMethod,
        ];
    }

    /**
     * @param  list<array{order_item_id: string, quantity: int, inventory_disposition?: string|null, exchange_variant_id?: string|null}>  $items
     * @return list<array<string, mixed>>
     */
    private function prepareItems(Order $order, PosReturnType $type, array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => ['Select at least one item to return.'],
            ]);
        }

        $prepared = [];
        foreach ($items as $index => $line) {
            $orderItem = OrderItem::query()->whereKey($line['order_item_id'] ?? null)->first();
            if ($orderItem === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.order_item_id" => ['Order item not found.'],
                ]);
            }

            $qty = (int) ($line['quantity'] ?? 0);
            $this->eligibility->assertItemQuantity($order, $orderItem, $qty);

            $disposition = InventoryDisposition::tryFrom((string) ($line['inventory_disposition'] ?? InventoryDisposition::Sellable->value))
                ?? InventoryDisposition::Sellable;

            $exchangeVariant = null;
            $exchangePrice = null;
            $unit = number_format((float) ($orderItem->unit_price_snapshot ?? $orderItem->unit_price ?? 0), 2, '.', '');
            $refundAmount = bcmul($unit, (string) $qty, 2);

            if ($type === PosReturnType::Exchange) {
                $resolution = ReturnItemResolution::Replacement;
                $exchangeVariant = ProductVariant::query()->find($line['exchange_variant_id'] ?? null);
                if ($exchangeVariant === null || $exchangeVariant->product_id !== $orderItem->product_id) {
                    throw ValidationException::withMessages([
                        "items.{$index}.exchange_variant_id" => ['Exchange must be the same product with a different variant.'],
                    ]);
                }
                if ($exchangeVariant->id === $orderItem->product_variant_id) {
                    throw ValidationException::withMessages([
                        "items.{$index}.exchange_variant_id" => ['Select a different variant for exchange.'],
                    ]);
                }
                $exchangePrice = number_format((float) ($exchangeVariant->price ?? $unit), 2, '.', '');
                $lineDiff = bcmul(bcsub($unit, $exchangePrice, 2), (string) $qty, 2);
                $refundAmount = bccomp($lineDiff, '0.00', 2) > 0 ? $lineDiff : '0.00';
            } else {
                $resolution = ReturnItemResolution::Refund;
            }

            $prepared[] = [
                'order_item' => $orderItem,
                'quantity' => $qty,
                'disposition' => $disposition,
                'resolution' => $resolution,
                'refund_amount' => $refundAmount,
                'exchange_variant' => $exchangeVariant,
                'exchange_unit_price' => $exchangePrice,
            ];
        }

        return $prepared;
    }

    private function applyInventory(
        Order $order,
        OrderItem $orderItem,
        int $quantity,
        InventoryDisposition $disposition,
        ?ProductVariant $exchangeVariant,
        Admin $cashier,
    ): void {
        $store = $order->store()->firstOrFail();
        $location = $this->stores->defaultLocation($store);

        if ($disposition->restocksSellable() && $orderItem->product_variant_id) {
            $variant = ProductVariant::query()->findOrFail($orderItem->product_variant_id);
            $inventory = $this->inventoryControl->resolveOrCreateInventory($variant, $location, true);
            $this->inventoryControl->recordReturn(
                $inventory,
                $quantity,
                $cashier,
                ReturnRequest::class,
                $order->id,
            );
            event(StorePlatformAudit::inventoryReturned($order, $orderItem->product_variant_id, $quantity, $cashier));
        } elseif ($disposition === InventoryDisposition::Damaged && $orderItem->product_variant_id) {
            $variant = ProductVariant::query()->findOrFail($orderItem->product_variant_id);
            $inventory = $this->inventoryControl->resolveOrCreateInventory($variant, $location, true);
            $this->inventoryControl->recordDamagedIntake(
                $inventory,
                $quantity,
                'POS return marked damaged',
                $cashier,
                ReturnRequest::class,
                $order->id,
            );
            event(StorePlatformAudit::inventoryMarkedDamaged($order, $orderItem->product_variant_id, $quantity, $cashier));
        }

        if ($exchangeVariant !== null) {
            $out = $this->inventoryControl->resolveOrCreateInventory($exchangeVariant, $location, true);
            if ($out->available() < $quantity) {
                throw ValidationException::withMessages([
                    'items' => ['Insufficient stock for exchange variant at this store.'],
                ]);
            }
            $this->inventoryControl->recordSale(
                $out,
                $quantity,
                $cashier,
                ReturnRequest::class,
                $order->id,
            );
        }
    }

    private function nextReturnNumber(string $storeCode): string
    {
        $prefix = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $storeCode) ?: 'POS');
        $year = now()->format('Y');
        $pattern = "RET-{$prefix}-{$year}-%";

        $latest = ReturnRequest::query()
            ->where('return_number', 'like', $pattern)
            ->lockForUpdate()
            ->orderByDesc('return_number')
            ->value('return_number');

        $next = 1;
        if ($latest) {
            $next = ((int) (string) str($latest)->afterLast('-')) + 1;
        }

        return sprintf('RET-%s-%s-%s', $prefix, $year, str_pad((string) $next, 6, '0', STR_PAD_LEFT));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReceiptSnapshot(ReturnRequest $return, ?RefundTransaction $refund): array
    {
        return [
            'return_number' => $return->return_number,
            'original_receipt_number' => $return->originalReceipt?->receipt_number,
            'order_number' => $return->order?->order_number,
            'store' => [
                'id' => $return->store_id,
                'name' => $return->store?->name,
                'code' => $return->store?->code,
            ],
            'cashier' => $return->processor?->name,
            'reason' => $return->returnReason?->name ?? $return->reason,
            'return_type' => $return->return_type,
            'refund_method' => $return->refund_method,
            'refund_amount' => $return->refund_total,
            'refund_reference' => $refund?->reference,
            'issued_at' => optional($return->completed_at)->toIso8601String(),
            'customer' => $return->order?->user?->name ?? 'Walk-in Customer',
            'lines' => $return->items->map(fn (ReturnItem $item) => [
                'name' => $item->orderItem?->product_name ?? $item->orderItem?->product_name_snapshot,
                'variant' => $item->orderItem?->variant_name ?? $item->orderItem?->variant_name_snapshot,
                'qty' => $item->quantity,
                'refund_amount' => $item->refund_amount,
                'disposition' => $item->inventory_disposition,
                'exchange_variant' => $item->exchangeVariant?->name,
                'exchange_sku' => $item->exchangeVariant?->sku,
            ])->all(),
        ];
    }
}

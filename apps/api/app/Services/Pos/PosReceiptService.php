<?php

namespace App\Services\Pos;

use App\Enums\PosReceiptLayout;
use App\Events\Audit\StorePlatformAudit;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PosReceipt;
use App\Models\PosSession;
use App\Models\Store;
use App\Models\User;
use App\Services\Pos\Receipt\PosReceiptNumberGenerator;
use App\Services\Pos\Receipt\PosReceiptRenderer;
use App\Services\Pos\Receipt\PosReceiptSnapshotBuilder;
use App\Services\Stores\ActiveStoreContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * POS Receipt Engine — printable projection of completed Orders.
 * Does not create sales, payments, or inventory movements.
 */
class PosReceiptService
{
    public function __construct(
        private readonly ActiveStoreContext $storeContext,
        private readonly PosReceiptNumberGenerator $numbers,
        private readonly PosReceiptSnapshotBuilder $snapshots,
        private readonly PosReceiptRenderer $renderer,
    ) {}

    /**
     * @param  list<array<string, mixed>>|null  $preparedLines
     */
    public function issueForSale(
        Order $order,
        Store $store,
        PosSession $session,
        Admin $cashier,
        Payment $payment,
        ?User $customer,
        ?string $change,
        ?float $amountReceived,
        ?array $preparedLines = null,
        ?array $promotion = null,
    ): PosReceipt {
        $session->loadMissing('terminal');

        $snapshot = $this->snapshots->build(
            $order,
            $store,
            $session,
            $cashier,
            $payment,
            $customer,
            $change,
            $amountReceived,
            $preparedLines,
            $promotion,
        );

        $receipt = PosReceipt::query()->create([
            'order_id' => $order->id,
            'pos_session_id' => $session->id,
            'store_id' => $store->id,
            'receipt_number' => $this->numbers->generate($store),
            'issued_at' => $order->paid_at ?? now(),
            'snapshot' => $snapshot,
            'print_count' => 0,
        ]);

        $receipt->forceFill([
            'qr_payload' => $this->snapshots->qrPayload($receipt),
        ])->save();

        event(StorePlatformAudit::receiptGenerated($receipt->fresh(), $cashier));

        return $receipt->fresh(['order', 'store', 'session.terminal']);
    }

    public function forOrder(Admin $admin, Order $order): PosReceipt
    {
        $receipt = PosReceipt::query()->where('order_id', $order->id)->first();
        if ($receipt === null) {
            throw ValidationException::withMessages([
                'receipt' => ['No receipt found for this order.'],
            ]);
        }

        return $this->show($admin, $receipt);
    }

    public function show(Admin $admin, PosReceipt $receipt): PosReceipt
    {
        $this->assertCanView($admin, $receipt);

        return $receipt->load(['order.user', 'order.payments', 'store', 'session.terminal', 'lastPrintedByAdmin']);
    }

    /**
     * @param  array{
     *   q?: string|null,
     *   receipt_number?: string|null,
     *   order_number?: string|null,
     *   customer?: string|null,
     *   store_id?: string|null,
     *   cashier_id?: string|null,
     *   from?: string|null,
     *   to?: string|null,
     *   per_page?: int
     * }  $filters
     * @return LengthAwarePaginator<int, PosReceipt>
     */
    public function search(Admin $admin, array $filters = []): LengthAwarePaginator
    {
        $query = PosReceipt::query()
            ->with(['order.user', 'store', 'session.terminal'])
            ->latest('issued_at');

        if (! $admin->is_super_admin) {
            $storeIds = $this->storeContext->assignedStores($admin)->pluck('id');
            $query->whereIn('store_id', $storeIds);
        }

        if (! empty($filters['store_id'])) {
            $store = Store::query()->findOrFail($filters['store_id']);
            $this->storeContext->assertCanAccess($admin, $store);
            $query->where('store_id', $store->id);
        }

        if (! empty($filters['receipt_number'])) {
            $query->where('receipt_number', 'like', '%'.$filters['receipt_number'].'%');
        }

        if (! empty($filters['order_number'])) {
            $query->whereHas('order', fn (Builder $q) => $q->where('order_number', 'like', '%'.$filters['order_number'].'%'));
        }

        if (! empty($filters['customer'])) {
            $term = $filters['customer'];
            $query->where(function (Builder $q) use ($term) {
                $q->where('snapshot->customer->name', 'like', "%{$term}%")
                    ->orWhereHas('order.user', function (Builder $u) use ($term) {
                        $u->where('name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%");
                    });
            });
        }

        if (! empty($filters['cashier_id'])) {
            $query->where('snapshot->cashier->id', $filters['cashier_id']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('issued_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('issued_at', '<=', $filters['to']);
        }

        if (! empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function (Builder $builder) use ($q) {
                $builder->where('receipt_number', 'like', "%{$q}%")
                    ->orWhereHas('order', fn (Builder $o) => $o->where('order_number', 'like', "%{$q}%"))
                    ->orWhere('snapshot->customer->name', 'like', "%{$q}%");
            });
        }

        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 25)));

        return $query->paginate($perPage);
    }

    /**
     * @return array{receipt: PosReceipt, html: string, layout: string}
     */
    public function print(Admin $admin, PosReceipt $receipt, PosReceiptLayout $layout = PosReceiptLayout::Thermal80): array
    {
        $receipt = $this->show($admin, $receipt);
        $html = $this->renderer->html($receipt, $layout === PosReceiptLayout::Pdf ? PosReceiptLayout::A4 : $layout);

        $receipt->forceFill([
            'print_count' => (int) $receipt->print_count + 1,
            'last_printed_at' => now(),
            'last_printed_by' => $admin->id,
        ])->save();

        event(StorePlatformAudit::receiptPrinted($receipt->fresh(), $admin, $layout));

        return [
            'receipt' => $receipt->fresh(['store', 'order', 'session.terminal']),
            'html' => $html,
            'layout' => $layout->value,
        ];
    }

    /**
     * Reprint never creates another order or receipt row.
     *
     * @return array{receipt: PosReceipt, html: string, layout: string}
     */
    public function reprint(Admin $admin, PosReceipt $receipt, PosReceiptLayout $layout = PosReceiptLayout::Thermal80): array
    {
        $receipt = $this->show($admin, $receipt);
        $html = $this->renderer->html($receipt, $layout === PosReceiptLayout::Pdf ? PosReceiptLayout::A4 : $layout);

        $receipt->forceFill([
            'print_count' => (int) $receipt->print_count + 1,
            'last_printed_at' => now(),
            'last_printed_by' => $admin->id,
        ])->save();

        $fresh = $receipt->fresh(['store', 'order', 'session.terminal']);
        event(StorePlatformAudit::receiptReprinted($fresh, $admin, $layout));
        \Illuminate\Support\Facades\Log::info('pos.receipt_reprint', [
            'receipt_id' => $fresh?->id,
            'receipt_number' => $fresh?->receipt_number,
            'admin_id' => $admin->id,
            'layout' => $layout->value,
            'print_count' => (int) ($fresh?->print_count ?? 0),
        ]);

        return [
            'receipt' => $fresh,
            'html' => $html,
            'layout' => $layout->value,
        ];
    }

    /**
     * @return array{receipt: PosReceipt, pdf: string, filename: string}
     */
    public function pdf(Admin $admin, PosReceipt $receipt): array
    {
        $receipt = $this->show($admin, $receipt);
        $pdf = $this->renderer->pdf($receipt);

        return [
            'receipt' => $receipt,
            'pdf' => $pdf,
            'filename' => $receipt->receipt_number.'.pdf',
        ];
    }

    public function previewHtml(Admin $admin, PosReceipt $receipt, PosReceiptLayout $layout = PosReceiptLayout::Thermal80): string
    {
        $receipt = $this->show($admin, $receipt);

        return $this->renderer->html($receipt, $layout);
    }

    private function assertCanView(Admin $admin, PosReceipt $receipt): void
    {
        if ($admin->is_super_admin) {
            return;
        }

        $store = $receipt->store ?? Store::query()->findOrFail($receipt->store_id);
        $this->storeContext->assertCanAccess($admin, $store);
    }
}

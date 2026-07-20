<?php

namespace App\Services\Procurement;

use App\Enums\PurchaseOrderStatus;
use App\Events\Procurement\PurchaseOrderConfirmed;
use App\Events\Procurement\PurchaseOrderCreated;
use App\Models\Admin;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Purchase order lifecycle. Does not touch inventory — ReceivingEngine does.
 */
class PurchaseOrderEngine
{
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return PurchaseOrder::query()
            ->with(['supplier:id,name,code,country', 'items'])
            ->when(filled($filters['status'] ?? null), fn ($q) => $q->where('status', $filters['status']))
            ->when(filled($filters['supplier_id'] ?? null), fn ($q) => $q->where('supplier_id', $filters['supplier_id']))
            ->when(filled($filters['search'] ?? null), function ($q) use ($filters) {
                $term = '%'.mb_strtolower(trim((string) $filters['search'])).'%';
                $q->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(purchase_number) LIKE ?', [$term])
                        ->orWhereHas('supplier', fn ($s) => $s->whereRaw('LOWER(name) LIKE ?', [$term]));
                });
            })
            ->latest()
            ->paginate($perPage);
    }

    public function show(PurchaseOrder $order): PurchaseOrder
    {
        return $order->load([
            'supplier',
            'items.variant.product',
            'receivingRecords.items.purchaseOrderItem',
            'receivingRecords.receivedByAdmin:id,name,email',
        ]);
    }

    /**
     * @param  array{
     *     supplier_id: string,
     *     order_id?: string|null,
     *     fulfillment_id?: string|null,
     *     idempotency_key?: string|null,
     *     currency?: string,
     *     notes?: string|null,
     *     items: list<array{product_variant_id: string, quantity_ordered: int, unit_cost: float|int|string, currency?: string}>
     * }  $data
     */
    public function create(array $data, ?Admin $admin = null): PurchaseOrder
    {
        $items = $data['items'] ?? [];
        if (! is_array($items) || $items === []) {
            throw ValidationException::withMessages([
                'items' => ['At least one purchase order item is required.'],
            ]);
        }

        if (! empty($data['idempotency_key'])) {
            $existing = PurchaseOrder::query()
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();
            if ($existing !== null) {
                return $this->show($existing);
            }
        }

        return DB::transaction(function () use ($data, $items, $admin) {
            $supplier = Supplier::query()
                ->where('id', $data['supplier_id'])
                ->where('is_active', true)
                ->firstOrFail();

            $currency = strtoupper((string) ($data['currency'] ?? 'TZS'));

            $order = PurchaseOrder::query()->create([
                'supplier_id' => $supplier->id,
                'order_id' => $data['order_id'] ?? null,
                'fulfillment_id' => $data['fulfillment_id'] ?? null,
                'purchase_number' => $this->generatePurchaseNumber(),
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'status' => PurchaseOrderStatus::Draft,
                'supplier_response' => 'pending',
                'currency' => $currency,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $index => $line) {
                $variantId = (string) ($line['product_variant_id'] ?? '');
                if (! ProductVariant::query()->whereKey($variantId)->exists()) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_variant_id" => ['Variant not found.'],
                    ]);
                }

                $qty = (int) ($line['quantity_ordered'] ?? 0);
                if ($qty < 1) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity_ordered" => ['Quantity must be at least 1.'],
                    ]);
                }

                PurchaseOrderItem::query()->create([
                    'purchase_order_id' => $order->id,
                    'product_variant_id' => $variantId,
                    'quantity_ordered' => $qty,
                    'quantity_received' => 0,
                    'unit_cost' => $line['unit_cost'],
                    'currency' => strtoupper((string) ($line['currency'] ?? $currency)),
                ]);
            }

            $order = $this->show($order);
            event(new PurchaseOrderCreated($order, $admin));

            return $order;
        });
    }

    /**
     * @param  array{status: string, notes?: string|null}  $data
     */
    public function updateStatus(PurchaseOrder $order, array $data, ?Admin $admin = null): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $data, $admin) {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $current = $locked->status;
            $target = PurchaseOrderStatus::tryFrom((string) $data['status']);

            if ($target === null) {
                throw ValidationException::withMessages([
                    'status' => ['Invalid purchase order status.'],
                ]);
            }

            if ($current === $target) {
                return $this->show($locked);
            }

            if (! in_array($target, $current->allowedTransitions(), true)) {
                throw ValidationException::withMessages([
                    'status' => [
                        "Cannot transition from {$current->value} to {$target->value}.",
                    ],
                ]);
            }

            $locked->status = $target;

            if ($target === PurchaseOrderStatus::Sent && $locked->ordered_at === null) {
                $locked->ordered_at = now();
            }

            if ($target === PurchaseOrderStatus::Confirmed) {
                $locked->confirmed_at = now();
                if ($locked->ordered_at === null) {
                    $locked->ordered_at = now();
                }
            }

            if ($target === PurchaseOrderStatus::Completed) {
                $locked->completed_at = now();
            }

            if (array_key_exists('notes', $data)) {
                $locked->notes = $data['notes'];
            }

            $locked->save();

            $fresh = $this->show($locked);

            if ($target === PurchaseOrderStatus::Confirmed) {
                event(new PurchaseOrderConfirmed($fresh, $admin));
            } else {
                event(new \App\Events\Procurement\PurchaseOrderStatusChanged($fresh, $current, $target, $admin));
            }

            return $fresh;
        });
    }

    public function applyReceivingStatus(PurchaseOrder $order): PurchaseOrder
    {
        $order->loadMissing('items');

        if ($order->isFullyReceived()) {
            $order->status = PurchaseOrderStatus::Completed;
            $order->completed_at = now();
        } elseif ($order->hasAnyReceived()) {
            $order->status = PurchaseOrderStatus::PartiallyReceived;
        }

        $order->save();

        return $order;
    }

    private function generatePurchaseNumber(): string
    {
        do {
            $number = 'PO-'.now()->format('Ymd').'-'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (PurchaseOrder::query()->where('purchase_number', $number)->exists());

        return $number;
    }
}

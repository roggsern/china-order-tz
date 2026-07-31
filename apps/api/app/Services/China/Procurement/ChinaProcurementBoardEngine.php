<?php

namespace App\Services\China\Procurement;

use App\Enums\ChinaProcurementRequirementStatus;
use App\Enums\ChinaWorkflowStage;
use App\Enums\CommerceChannelCode;
use App\Events\Audit\ChinaPurchaseCompletedAudit;
use App\Events\Audit\ChinaPurchaseMarkedPurchasedAudit;
use App\Events\Audit\ChinaPurchaseRequirementCancelledAudit;
use App\Events\Audit\ChinaPurchaseRequirementCreatedAudit;
use App\Models\Admin;
use App\Models\ChinaProcurementRequirement;
use App\Models\ChinaProcurementRequirementLink;
use App\Models\ChinaWorkflowRecord;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SupplierProduct;
use App\Services\Commerce\CommerceChannelResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChinaProcurementBoardEngine
{
    public function __construct(
        private readonly ChinaCommercialStockService $commercialStock,
        private readonly CommerceChannelResolver $channels,
    ) {}

    public function recordPaidOrderDemand(Order $order): void
    {
        if (! $this->isChinaImportOrder($order)) {
            return;
        }

        $order->loadMissing(['items.product.commerceChannel', 'items.variant']);

        DB::transaction(function () use ($order): void {
            foreach ($order->items as $item) {
                if (! $this->isChinaImportItem($item)) {
                    continue;
                }

                if ($this->hasProcessedPaidItem($item)) {
                    continue;
                }

                $this->commercialStock->reserveForPaidItem($item);
                $requirement = $this->upsertRequirementForItem($item);
                $this->attachOrderLink($requirement, $item);

                event(ChinaPurchaseRequirementCreatedAudit::fromRequirement($requirement, $order));
            }
        });
    }

    public function reversePaidOrderDemand(Order $order, ?Admin $admin = null): void
    {
        if (! $this->isChinaImportOrder($order)) {
            return;
        }

        $order->loadMissing(['items.product.commerceChannel', 'items.variant']);

        DB::transaction(function () use ($order, $admin): void {
            $links = ChinaProcurementRequirementLink::query()
                ->where('order_id', $order->id)
                ->with(['requirement', 'orderItem'])
                ->lockForUpdate()
                ->get();

            if ($links->isEmpty()) {
                return;
            }

            foreach ($links as $link) {
                $item = $link->orderItem;
                if ($item !== null) {
                    $this->commercialStock->releaseForCancelledItem($item);
                }

                $requirement = $link->requirement;
                if ($requirement !== null) {
                    $requirement->forceFill([
                        'quantity_required' => max(
                            0,
                            (int) $requirement->quantity_required - max(1, (int) $link->quantity),
                        ),
                    ])->save();
                }

                $link->delete();
            }

            event(ChinaPurchaseRequirementCancelledAudit::fromOrder($order, $admin, $links->count()));
        });
    }

    public function hasProcessedPaidItem(OrderItem $item): bool
    {
        return ChinaProcurementRequirementLink::query()
            ->where('order_item_id', $item->id)
            ->exists();
    }

    /**
     * @param  array{
     *     status?: string|null,
     *     supplier_id?: string|null,
     *     product_id?: string|null,
     *     product_variant_id?: string|null,
     *     category_id?: string|null,
     *     from?: string|null,
     *     to?: string|null,
     *     per_page?: int|null,
     * }  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = ChinaProcurementRequirement::query()
            ->with([
                'product:id,name,slug,category_id,supplier_id',
                'product.category:id,name,slug',
                'variant:id,product_id,sku,name',
                'supplier:id,name,code',
                'links.order:id,order_number,placed_at',
            ])
            ->orderByDesc('updated_at');

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        if (filled($filters['supplier_id'] ?? null)) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (filled($filters['product_id'] ?? null)) {
            $query->where('product_id', $filters['product_id']);
        }

        if (filled($filters['product_variant_id'] ?? null)) {
            $query->where('product_variant_id', $filters['product_variant_id']);
        }

        if (filled($filters['category_id'] ?? null)) {
            $query->whereHas('product', fn (Builder $q) => $q->where('category_id', $filters['category_id']));
        }

        if (filled($filters['from'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (filled($filters['to'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 25), 1), 100);

        return $query->paginate($perPage);
    }

    public function show(ChinaProcurementRequirement $requirement): ChinaProcurementRequirement
    {
        return $requirement->load([
            'product.category',
            'variant',
            'supplier',
            'links.order:id,order_number,status,placed_at',
            'links.orderItem:id,quantity,variant_name_snapshot,attributes_snapshot',
        ]);
    }

    public function markPurchased(
        Admin $admin,
        ChinaProcurementRequirement $requirement,
        int $quantityPurchased,
    ): ChinaProcurementRequirement {
        if ($quantityPurchased <= 0) {
            throw ValidationException::withMessages([
                'quantity_purchased' => ['Purchased quantity must be greater than zero.'],
            ]);
        }

        return DB::transaction(function () use ($admin, $requirement, $quantityPurchased): ChinaProcurementRequirement {
            /** @var ChinaProcurementRequirement $locked */
            $locked = ChinaProcurementRequirement::query()
                ->whereKey($requirement->id)
                ->lockForUpdate()
                ->firstOrFail();

            $newPurchased = min(
                (int) $locked->quantity_required,
                (int) $locked->quantity_purchased + $quantityPurchased,
            );

            $status = $newPurchased >= (int) $locked->quantity_required
                ? ChinaProcurementRequirementStatus::Purchased
                : ChinaProcurementRequirementStatus::Purchasing;

            $locked->forceFill([
                'quantity_purchased' => $newPurchased,
                'status' => $status,
            ])->save();

            $this->syncLinkedOrdersPurchased($locked);

            event(ChinaPurchaseMarkedPurchasedAudit::fromRequirement($locked, $admin, $quantityPurchased));

            return $this->show($locked->fresh() ?? $locked);
        });
    }

    public function startQc(Admin $admin, ChinaProcurementRequirement $requirement): ChinaProcurementRequirement
    {
        return DB::transaction(function () use ($admin, $requirement): ChinaProcurementRequirement {
            /** @var ChinaProcurementRequirement $locked */
            $locked = ChinaProcurementRequirement::query()
                ->whereKey($requirement->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $locked->quantity_purchased <= 0) {
                throw ValidationException::withMessages([
                    'status' => ['At least one unit must be purchased before starting QC.'],
                ]);
            }

            $locked->forceFill([
                'status' => ChinaProcurementRequirementStatus::QcPending,
            ])->save();

            $this->syncLinkedOrdersQcPending($locked);

            return $this->show($locked->fresh() ?? $locked);
        });
    }

    public function complete(Admin $admin, ChinaProcurementRequirement $requirement): ChinaProcurementRequirement
    {
        return DB::transaction(function () use ($admin, $requirement): ChinaProcurementRequirement {
            /** @var ChinaProcurementRequirement $locked */
            $locked = ChinaProcurementRequirement::query()
                ->whereKey($requirement->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $locked->quantity_purchased < (int) $locked->quantity_required) {
                throw ValidationException::withMessages([
                    'status' => ['All required units must be purchased before completion.'],
                ]);
            }

            $locked->forceFill([
                'status' => ChinaProcurementRequirementStatus::Completed,
            ])->save();

            event(ChinaPurchaseCompletedAudit::fromRequirement($locked, $admin));

            return $this->show($locked->fresh() ?? $locked);
        });
    }

    private function upsertRequirementForItem(OrderItem $item): ChinaProcurementRequirement
    {
        $variantId = $item->product_variant_id;
        $attributes = $this->extractVariantAttributes($item);

        $requirement = ChinaProcurementRequirement::query()
            ->where('product_id', $item->product_id)
            ->where('product_variant_id', $variantId)
            ->whereIn('status', [
                ChinaProcurementRequirementStatus::Pending->value,
                ChinaProcurementRequirementStatus::Purchasing->value,
            ])
            ->lockForUpdate()
            ->first();

        if ($requirement === null) {
            $requirement = ChinaProcurementRequirement::query()->create([
                'product_id' => $item->product_id,
                'product_variant_id' => $variantId,
                'supplier_id' => $this->resolveSupplierId($item),
                'quantity_required' => 0,
                'quantity_purchased' => 0,
                'status' => ChinaProcurementRequirementStatus::Pending,
                'variant_attributes' => $attributes,
            ]);
        }

        $requirement->forceFill([
            'quantity_required' => (int) $requirement->quantity_required + max(1, (int) $item->quantity),
            'supplier_id' => $requirement->supplier_id ?? $this->resolveSupplierId($item),
            'variant_attributes' => $attributes ?: $requirement->variant_attributes,
        ])->save();

        return $requirement;
    }

    private function attachOrderLink(ChinaProcurementRequirement $requirement, OrderItem $item): void
    {
        $link = ChinaProcurementRequirementLink::query()->firstOrNew([
            'requirement_id' => $requirement->id,
            'order_item_id' => $item->id,
        ]);

        $link->forceFill([
            'order_id' => $item->order_id,
            'quantity' => max(1, (int) $item->quantity),
        ])->save();
    }

    private function syncLinkedOrdersPurchased(ChinaProcurementRequirement $requirement): void
    {
        $orderIds = $requirement->links()->pluck('order_id')->unique()->values();

        foreach ($orderIds as $orderId) {
            $record = ChinaWorkflowRecord::query()->where('order_id', $orderId)->first();
            if ($record === null) {
                continue;
            }

            if (in_array($record->stage, [
                ChinaWorkflowStage::AwaitingProcurement,
                ChinaWorkflowStage::ProcurementInProgress,
            ], true)) {
                $record->forceFill(['stage' => ChinaWorkflowStage::ProcurementInProgress])->save();
            }
        }
    }

    private function syncLinkedOrdersQcPending(ChinaProcurementRequirement $requirement): void
    {
        $orderIds = $requirement->links()->pluck('order_id')->unique()->values();

        foreach ($orderIds as $orderId) {
            $record = ChinaWorkflowRecord::query()->where('order_id', $orderId)->first();
            if ($record === null) {
                continue;
            }

            if ($record->stage !== ChinaWorkflowStage::QcPending) {
                $record->forceFill(['stage' => ChinaWorkflowStage::QcPending])->save();
            }
        }
    }

    private function resolveSupplierId(OrderItem $item): ?string
    {
        if ($item->product_variant_id !== null) {
            $mapping = SupplierProduct::query()
                ->where('product_variant_id', $item->product_variant_id)
                ->where('is_active', true)
                ->whereHas('supplier', fn ($q) => $q->where('is_active', true))
                ->orderByDesc('updated_at')
                ->first();

            if ($mapping !== null) {
                return $mapping->supplier_id;
            }
        }

        $item->loadMissing('product');

        return $item->product?->supplier_id;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractVariantAttributes(OrderItem $item): array
    {
        $attributes = is_array($item->attributes_snapshot) ? $item->attributes_snapshot : [];

        if ($item->variant_name_snapshot) {
            $attributes['variant_name'] = $item->variant_name_snapshot;
        }

        return $attributes;
    }

    private function isChinaImportOrder(Order $order): bool
    {
        $channel = $this->channels->resolveOrderChannel($order);

        return CommerceChannelCode::tryFrom($channel->code) === CommerceChannelCode::ChinaImport;
    }

    private function isChinaImportItem(OrderItem $item): bool
    {
        $item->loadMissing('product.commerceChannel');
        $product = $item->product;
        if ($product === null) {
            return false;
        }

        $channel = $this->channels->resolveProductChannel($product);

        return CommerceChannelCode::tryFrom($channel->code) === CommerceChannelCode::ChinaImport;
    }
}

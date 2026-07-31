<?php

namespace App\Services\Warehouse;

use App\Enums\WarehouseStockTransferStatus;
use App\Events\Audit\TransferCompletedAudit;
use App\Events\Audit\TransferCreatedAudit;
use App\Models\Admin;
use App\Models\ProductVariant;
use App\Models\WarehouseFacility;
use App\Models\WarehouseStockTransfer;
use App\Models\WarehouseStockTransferLine;
use App\Services\Inventory\InventoryControlEngine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseTransferService
{
    public function __construct(
        private readonly InventoryControlEngine $inventory,
    ) {}

    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = WarehouseStockTransfer::query()
            ->with(['fromFacility', 'toFacility', 'requestedByAdmin', 'lines.productVariant'])
            ->latest();

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        return $query->paginate(max(1, min($perPage, 100)));
    }

    public function show(WarehouseStockTransfer $transfer): WarehouseStockTransfer
    {
        return $transfer->load([
            'fromFacility',
            'toFacility',
            'requestedByAdmin',
            'approvedByAdmin',
            'lines.productVariant.product',
        ]);
    }

    /**
     * @param  array{
     *     from_facility_id: string,
     *     to_facility_id: string,
     *     notes?: string|null,
     *     lines: list<array{product_variant_id: string, quantity: int}>
     * }  $input
     */
    public function create(array $input, Admin $admin): WarehouseStockTransfer
    {
        if ($input['from_facility_id'] === $input['to_facility_id']) {
            throw ValidationException::withMessages([
                'to_facility_id' => ['Source and destination warehouses must differ.'],
            ]);
        }

        $from = WarehouseFacility::query()->findOrFail($input['from_facility_id']);
        $to = WarehouseFacility::query()->findOrFail($input['to_facility_id']);

        if (! $from->inventory_warehouse_code || ! $to->inventory_warehouse_code) {
            throw ValidationException::withMessages([
                'facility' => ['Both warehouses must have inventory warehouse codes configured for stock transfer.'],
            ]);
        }

        if (empty($input['lines'])) {
            throw ValidationException::withMessages(['lines' => ['At least one transfer line is required.']]);
        }

        $transfer = DB::transaction(function () use ($input, $admin, $from, $to): WarehouseStockTransfer {
            $transfer = WarehouseStockTransfer::query()->create([
                'transfer_number' => $this->nextNumber(),
                'from_facility_id' => $from->id,
                'to_facility_id' => $to->id,
                'status' => WarehouseStockTransferStatus::Requested,
                'requested_by_admin_id' => $admin->id,
                'notes' => $input['notes'] ?? null,
                'requested_at' => now(),
            ]);

            foreach ($input['lines'] as $line) {
                $qty = (int) ($line['quantity'] ?? 0);
                if ($qty <= 0) {
                    throw ValidationException::withMessages([
                        'lines' => ['Transfer line quantity must be greater than zero.'],
                    ]);
                }

                ProductVariant::query()->findOrFail($line['product_variant_id']);

                WarehouseStockTransferLine::query()->create([
                    'transfer_id' => $transfer->id,
                    'product_variant_id' => $line['product_variant_id'],
                    'quantity' => $qty,
                ]);
            }

            return $transfer;
        });

        event(TransferCreatedAudit::fromTransfer($this->show($transfer), $admin));

        return $this->show($transfer);
    }

    public function approve(WarehouseStockTransfer $transfer, Admin $admin): WarehouseStockTransfer
    {
        return $this->transition($transfer, WarehouseStockTransferStatus::Approved, $admin, [
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
        ]);
    }

    public function complete(WarehouseStockTransfer $transfer, Admin $admin): WarehouseStockTransfer
    {
        return DB::transaction(function () use ($transfer, $admin): WarehouseStockTransfer {
            $transfer = $this->show($this->lock($transfer));
            $current = $this->resolveStatus($transfer);

            if ($current !== WarehouseStockTransferStatus::Approved) {
                throw ValidationException::withMessages([
                    'status' => ['Transfer must be approved before completion.'],
                ]);
            }

            $fromCode = $transfer->fromFacility?->inventory_warehouse_code;
            $toCode = $transfer->toFacility?->inventory_warehouse_code;

            if (! $fromCode || ! $toCode) {
                throw ValidationException::withMessages([
                    'facility' => ['Warehouse inventory codes are required to move stock.'],
                ]);
            }

            foreach ($transfer->lines as $line) {
                $variant = $line->productVariant;
                if ($variant === null) {
                    continue;
                }

                $qty = (int) $line->quantity;
                $refType = WarehouseStockTransfer::class;
                $refId = $transfer->id;

                $this->inventory->adjustWarehouseCode(
                    $variant,
                    $fromCode,
                    -$qty,
                    $admin,
                    "Warehouse transfer {$transfer->transfer_number} out",
                    $refType,
                    $refId,
                    idempotencyKey: "wh-transfer-out:{$transfer->id}:{$line->id}",
                );

                $this->inventory->adjustWarehouseCode(
                    $variant,
                    $toCode,
                    $qty,
                    $admin,
                    "Warehouse transfer {$transfer->transfer_number} in",
                    $refType,
                    $refId,
                    idempotencyKey: "wh-transfer-in:{$transfer->id}:{$line->id}",
                );
            }

            $transfer->status = WarehouseStockTransferStatus::Transferred;
            $transfer->transferred_at = now();
            $transfer->save();

            event(TransferCompletedAudit::fromTransfer($transfer->fresh(), $admin));

            return $this->show($transfer->fresh());
        });
    }

    public function cancel(WarehouseStockTransfer $transfer, Admin $admin): WarehouseStockTransfer
    {
        return $this->transition($transfer, WarehouseStockTransferStatus::Cancelled, $admin, [
            'cancelled_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function transition(
        WarehouseStockTransfer $transfer,
        WarehouseStockTransferStatus $next,
        Admin $admin,
        array $attributes = [],
    ): WarehouseStockTransfer {
        return DB::transaction(function () use ($transfer, $next, $admin, $attributes): WarehouseStockTransfer {
            $locked = $this->lock($transfer);
            $current = $this->resolveStatus($locked);

            if (! $current->canTransitionTo($next)) {
                throw ValidationException::withMessages([
                    'status' => ["Cannot transition transfer from [{$current->value}] to [{$next->value}]."],
                ]);
            }

            $locked->status = $next;
            foreach ($attributes as $key => $value) {
                $locked->{$key} = $value;
            }
            $locked->save();

            return $this->show($locked->fresh());
        });
    }

    private function lock(WarehouseStockTransfer $transfer): WarehouseStockTransfer
    {
        return WarehouseStockTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
    }

    private function resolveStatus(WarehouseStockTransfer $transfer): WarehouseStockTransferStatus
    {
        return $transfer->status instanceof WarehouseStockTransferStatus
            ? $transfer->status
            : WarehouseStockTransferStatus::from((string) $transfer->status);
    }

    private function nextNumber(): string
    {
        $date = now()->format('Ymd');
        $count = WarehouseStockTransfer::query()
            ->whereDate('created_at', now()->toDateString())
            ->count() + 1;

        return sprintf('WH-TX-%s-%04d', $date, $count);
    }
}

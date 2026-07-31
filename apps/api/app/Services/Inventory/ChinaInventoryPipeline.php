<?php

namespace App\Services\Inventory;

use App\Enums\ChinaInventoryTransferStatus;
use App\Enums\InventoryWarehouseCode;
use App\Models\Admin;
use App\Models\ChinaInventoryTransfer;
use App\Models\ChinaInventoryTransferLine;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * China → Tanzania catalog stock pipeline.
 *
 * China / in-transit warehouses are never commerce-sellable (StockResolver defaults to MAIN).
 * Mutations go through InventoryControlEngine only (InventoryMutationGate unchanged).
 */
final class ChinaInventoryPipeline
{
    public function __construct(
        private readonly InventoryControlEngine $inventory,
    ) {}

    /**
     * Supplier → China receiving. Increases CHINA warehouse only.
     *
     * @param  list<array{product_variant_id: string, quantity: int}>  $lines
     */
    public function receiveInChina(array $lines, ?Admin $actor = null, ?string $notes = null): ChinaInventoryTransfer
    {
        $normalized = $this->normalizeLines($lines);

        return DB::transaction(function () use ($normalized, $actor, $notes) {
            $transfer = ChinaInventoryTransfer::query()->create([
                'transfer_number' => $this->nextTransferNumber(),
                'status' => ChinaInventoryTransferStatus::ReceivedChina,
                'created_by' => $actor?->id,
                'notes' => $notes,
                'received_china_at' => now(),
            ]);

            foreach ($normalized as $line) {
                /** @var ProductVariant $variant */
                $variant = ProductVariant::query()->whereKey($line['product_variant_id'])->lockForUpdate()->firstOrFail();

                ChinaInventoryTransferLine::query()->create([
                    'china_inventory_transfer_id' => $transfer->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => $line['quantity'],
                ]);

                $this->inventory->receiveToWarehouseCode(
                    $variant,
                    InventoryWarehouseCode::China->value,
                    $line['quantity'],
                    $actor,
                    'China warehouse receiving',
                    ChinaInventoryTransfer::class,
                    $transfer->id,
                );
            }

            return $transfer->fresh(['lines']) ?? $transfer;
        });
    }

    public function startQualityCheck(ChinaInventoryTransfer $transfer): ChinaInventoryTransfer
    {
        return $this->transition($transfer, ChinaInventoryTransferStatus::QualityCheck, [
            'quality_checked_at' => now(),
        ]);
    }

    public function markReadyForExport(ChinaInventoryTransfer $transfer): ChinaInventoryTransfer
    {
        return $this->transition($transfer, ChinaInventoryTransferStatus::ReadyForExport, [
            'ready_for_export_at' => now(),
        ]);
    }

    /**
     * Export allocation: deduct China warehouse, create in-transit stock.
     */
    public function allocateShipment(ChinaInventoryTransfer $transfer, ?Admin $actor = null): ChinaInventoryTransfer
    {
        return DB::transaction(function () use ($transfer, $actor) {
            $locked = $this->lockTransfer($transfer);
            $this->assertCanTransition($locked, ChinaInventoryTransferStatus::Shipment);

            foreach ($locked->lines as $line) {
                /** @var ProductVariant $variant */
                $variant = ProductVariant::query()->whereKey($line->product_variant_id)->lockForUpdate()->firstOrFail();
                $qty = (int) $line->quantity;

                $this->inventory->adjustWarehouseCode(
                    $variant,
                    InventoryWarehouseCode::China->value,
                    -1 * $qty,
                    $actor,
                    'China export shipment allocation',
                    ChinaInventoryTransfer::class,
                    $locked->id,
                    'china-export-out:'.$locked->id.':'.$variant->id,
                );

                $this->inventory->receiveToWarehouseCode(
                    $variant,
                    InventoryWarehouseCode::InTransit->value,
                    $qty,
                    $actor,
                    'China → TZ in-transit allocation',
                    ChinaInventoryTransfer::class,
                    $locked->id,
                );
            }

            $locked->forceFill([
                'status' => ChinaInventoryTransferStatus::Shipment,
                'shipped_at' => now(),
            ])->save();

            return $locked->fresh(['lines']) ?? $locked;
        });
    }

    public function markInTransit(ChinaInventoryTransfer $transfer): ChinaInventoryTransfer
    {
        return $this->transition($transfer, ChinaInventoryTransferStatus::InTransit, [
            'in_transit_at' => now(),
        ]);
    }

    /**
     * Arrival in Tanzania — stock remains IN_TRANSIT (not sellable yet).
     */
    public function markArrivedTanzania(ChinaInventoryTransfer $transfer): ChinaInventoryTransfer
    {
        return $this->transition($transfer, ChinaInventoryTransferStatus::ArrivedTanzania, [
            'arrived_tanzania_at' => now(),
        ]);
    }

    /**
     * Tanzania warehouse receive — move IN_TRANSIT → MAIN (sellable).
     */
    public function receiveInTanzania(ChinaInventoryTransfer $transfer, ?Admin $actor = null): ChinaInventoryTransfer
    {
        return DB::transaction(function () use ($transfer, $actor) {
            $locked = $this->lockTransfer($transfer);
            $this->assertCanTransition($locked, ChinaInventoryTransferStatus::ReceivedTanzania);

            foreach ($locked->lines as $line) {
                /** @var ProductVariant $variant */
                $variant = ProductVariant::query()->whereKey($line->product_variant_id)->lockForUpdate()->firstOrFail();
                $qty = (int) $line->quantity;

                $this->inventory->adjustWarehouseCode(
                    $variant,
                    InventoryWarehouseCode::InTransit->value,
                    -1 * $qty,
                    $actor,
                    'Tanzania warehouse receive from transit',
                    ChinaInventoryTransfer::class,
                    $locked->id,
                    'china-tz-receive-out:'.$locked->id.':'.$variant->id,
                );

                $this->inventory->receiveToWarehouseCode(
                    $variant,
                    InventoryWarehouseCode::Main->value,
                    $qty,
                    $actor,
                    'Tanzania sellable warehouse receive',
                    ChinaInventoryTransfer::class,
                    $locked->id,
                );
            }

            $locked->forceFill([
                'status' => ChinaInventoryTransferStatus::ReceivedTanzania,
                'received_tanzania_at' => now(),
            ])->save();

            return $locked->fresh(['lines']) ?? $locked;
        });
    }

    /**
     * Cancelled shipment restores China stock from in-transit (when allocated).
     */
    public function cancelShipment(ChinaInventoryTransfer $transfer, ?Admin $actor = null): ChinaInventoryTransfer
    {
        return DB::transaction(function () use ($transfer, $actor) {
            $locked = $this->lockTransfer($transfer);

            if ($locked->status === ChinaInventoryTransferStatus::Cancelled) {
                return $locked;
            }

            if ($locked->status === ChinaInventoryTransferStatus::ReceivedTanzania) {
                throw ValidationException::withMessages([
                    'status' => ['Cannot cancel a transfer already received into Tanzania sellable stock.'],
                ]);
            }

            $allocated = in_array($locked->status, [
                ChinaInventoryTransferStatus::Shipment,
                ChinaInventoryTransferStatus::InTransit,
                ChinaInventoryTransferStatus::ArrivedTanzania,
            ], true);

            if ($allocated) {
                foreach ($locked->lines as $line) {
                    /** @var ProductVariant $variant */
                    $variant = ProductVariant::query()->whereKey($line->product_variant_id)->lockForUpdate()->firstOrFail();
                    $qty = (int) $line->quantity;

                    $this->inventory->adjustWarehouseCode(
                        $variant,
                        InventoryWarehouseCode::InTransit->value,
                        -1 * $qty,
                        $actor,
                        'Cancelled China shipment — clear transit',
                        ChinaInventoryTransfer::class,
                        $locked->id,
                        'china-cancel-transit:'.$locked->id.':'.$variant->id,
                    );

                    $this->inventory->receiveToWarehouseCode(
                        $variant,
                        InventoryWarehouseCode::China->value,
                        $qty,
                        $actor,
                        'Cancelled China shipment — restore China stock',
                        ChinaInventoryTransfer::class,
                        $locked->id,
                    );
                }
            }

            $locked->forceFill([
                'status' => ChinaInventoryTransferStatus::Cancelled,
                'cancelled_at' => now(),
            ])->save();

            return $locked->fresh(['lines']) ?? $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function transition(
        ChinaInventoryTransfer $transfer,
        ChinaInventoryTransferStatus $next,
        array $attributes = [],
    ): ChinaInventoryTransfer {
        return DB::transaction(function () use ($transfer, $next, $attributes) {
            $locked = $this->lockTransfer($transfer);
            $this->assertCanTransition($locked, $next);
            $locked->forceFill(array_merge(['status' => $next], $attributes))->save();

            return $locked->fresh(['lines']) ?? $locked;
        });
    }

    private function lockTransfer(ChinaInventoryTransfer $transfer): ChinaInventoryTransfer
    {
        return ChinaInventoryTransfer::query()
            ->whereKey($transfer->id)
            ->lockForUpdate()
            ->with('lines')
            ->firstOrFail();
    }

    private function assertCanTransition(
        ChinaInventoryTransfer $transfer,
        ChinaInventoryTransferStatus $next,
    ): void {
        $current = $transfer->status instanceof ChinaInventoryTransferStatus
            ? $transfer->status
            : ChinaInventoryTransferStatus::from((string) $transfer->status);

        if (! $current->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => [
                    "Cannot transition China inventory transfer from [{$current->value}] to [{$next->value}].",
                ],
            ]);
        }
    }

    /**
     * @param  list<array{product_variant_id: string, quantity: int}>  $lines
     * @return list<array{product_variant_id: string, quantity: int}>
     */
    private function normalizeLines(array $lines): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => ['At least one transfer line is required.'],
            ]);
        }

        $normalized = [];
        foreach ($lines as $index => $line) {
            $variantId = (string) ($line['product_variant_id'] ?? '');
            $qty = (int) ($line['quantity'] ?? 0);
            if ($variantId === '' || $qty < 1) {
                throw ValidationException::withMessages([
                    "lines.{$index}" => ['Each line requires product_variant_id and quantity >= 1.'],
                ]);
            }
            if (! ProductVariant::query()->whereKey($variantId)->exists()) {
                throw ValidationException::withMessages([
                    "lines.{$index}.product_variant_id" => ['Product variant not found.'],
                ]);
            }
            $normalized[] = [
                'product_variant_id' => $variantId,
                'quantity' => $qty,
            ];
        }

        return $normalized;
    }

    private function nextTransferNumber(): string
    {
        return 'CIT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }
}

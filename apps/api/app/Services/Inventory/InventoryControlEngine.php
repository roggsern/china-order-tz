<?php

namespace App\Services\Inventory;

use App\Enums\InventoryCountScope;
use App\Enums\InventoryCountStatus;
use App\Enums\InventoryMovementType;
use App\Events\Audit\InventoryControlAudit;
use App\Models\Admin;
use App\Models\InventoryCountLine;
use App\Models\InventoryCountSession;
use App\Models\InventoryLocation;
use App\Models\InventoryStockMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\VariantInventory;
use App\Services\Stores\StoreService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Inventory control over VariantInventory — ledger, counts, adjustments, valuation.
 * Sellable mutations go through InventoryMutationGate (ADR 055).
 * Does not replace VariantInventory as Catalog Stock SSoT.
 */
class InventoryControlEngine
{
    public function __construct(
        private readonly StoreService $stores,
        private readonly InventoryMutationGate $mutationGate,
        private readonly CanonicalVariantInventoryInitializer $canonicalInitializer,
    ) {}

    public function resolveOrCreateInventory(
        ProductVariant $variant,
        InventoryLocation $location,
        bool $lock = true,
    ): VariantInventory {
        return DB::transaction(function () use ($variant, $location, $lock) {
            // Establish MAIN/location row without shadowing positive legacy stock (RC1-B1).
            $inventory = $this->canonicalInitializer->ensure($variant, [
                'warehouse_code' => $location->code,
                'inventory_location_id' => $location->id,
                'requested_on_hand' => null,
                'reorder_level' => 5,
                'safety_stock' => 0,
                'is_active' => true,
                'reason' => 'Inventory control bootstrap — opening stock from legacy',
            ]);

            if ($inventory->inventory_location_id === null
                || (string) $inventory->inventory_location_id !== (string) $location->id
            ) {
                $inventory->forceFill([
                    'inventory_location_id' => $location->id,
                    'warehouse_code' => $location->code,
                ])->save();
            }

            return $lock
                ? VariantInventory::query()->whereKey($inventory->id)->lockForUpdate()->firstOrFail()
                : ($inventory->fresh() ?? $inventory);
        });
    }

    /**
     * Apply a signed change to sellable on_hand and append a ledger row.
     * Delegates to InventoryMutationGate (canonical write authority).
     */
    public function mutateSellable(
        VariantInventory $inventory,
        InventoryMovementType $type,
        int $quantityChange,
        ?Admin $actor = null,
        ?string $reason = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?array $metadata = null,
        int $damagedDelta = 0,
        int $inspectionDelta = 0,
        ?string $idempotencyKey = null,
    ): InventoryStockMovement {
        $kind = InventoryMutationGate::kindFromMovementType($type);
        $adjustSubtype = $type === InventoryMovementType::Correction ? 'correction' : null;

        $result = $this->mutationGate->mutateVariantSellable(
            inventory: $inventory,
            kind: $kind,
            quantityChange: $quantityChange,
            actor: $actor,
            reason: $reason,
            referenceType: $referenceType,
            referenceId: $referenceId,
            metadata: $metadata,
            damagedDelta: $damagedDelta,
            inspectionDelta: $inspectionDelta,
            adjustSubtype: $adjustSubtype,
            idempotencyKey: $idempotencyKey,
        );

        $movement = $result->movement;
        if (! $movement instanceof InventoryStockMovement) {
            throw ValidationException::withMessages([
                'inventory' => ['Variant mutation did not produce a stock movement.'],
            ]);
        }

        return $movement;
    }

    public function receiveToLocation(
        ProductVariant $variant,
        InventoryLocation $location,
        int $qty,
        ?Admin $actor = null,
        ?string $reason = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
    ): InventoryStockMovement {
        if ($qty < 1) {
            throw ValidationException::withMessages(['quantity' => ['Receive quantity must be at least 1.']]);
        }

        return DB::transaction(function () use ($variant, $location, $qty, $actor, $reason, $referenceType, $referenceId) {
            $inventory = $this->resolveOrCreateInventory($variant, $location, true);

            // Audit for receiving is emitted by Procurement (InventoryReceivedAudit).
            return $this->mutateSellable(
                $inventory,
                InventoryMovementType::Receive,
                $qty,
                $actor,
                $reason ?? 'Goods received',
                $referenceType,
                $referenceId,
            );
        });
    }

    /**
     * Legacy warehouse_code receive (no store location). Prefer receiveToLocation for multi-store.
     */
    public function receiveToWarehouseCode(
        ProductVariant $variant,
        string $warehouseCode,
        int $qty,
        ?Admin $actor = null,
        ?string $reason = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
    ): InventoryStockMovement {
        if ($qty < 1) {
            throw ValidationException::withMessages(['quantity' => ['Receive quantity must be at least 1.']]);
        }

        return DB::transaction(function () use ($variant, $warehouseCode, $qty, $actor, $reason, $referenceType, $referenceId) {
            $inventory = $this->canonicalInitializer->ensure($variant, [
                'warehouse_code' => $warehouseCode,
                'requested_on_hand' => null,
                'reorder_level' => 0,
                'safety_stock' => 0,
                'is_active' => true,
                'actor' => $actor,
                'reason' => 'Inventory control warehouse bootstrap — opening stock from legacy',
            ]);

            $inventory = VariantInventory::query()->whereKey($inventory->id)->lockForUpdate()->firstOrFail();

            return $this->mutateSellable(
                $inventory,
                InventoryMovementType::Receive,
                $qty,
                $actor,
                $reason ?? 'Goods received',
                $referenceType,
                $referenceId,
            );
        });
    }

    public function recordSale(
        VariantInventory $inventory,
        int $qty,
        ?Admin $actor = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
    ): InventoryStockMovement {
        return $this->mutateSellable(
            $inventory,
            InventoryMovementType::Sale,
            -1 * abs($qty),
            $actor,
            'POS / order sale',
            $referenceType,
            $referenceId,
        );
    }

    public function recordReturn(
        VariantInventory $inventory,
        int $qty,
        ?Admin $actor = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $idempotencyKey = null,
    ): InventoryStockMovement {
        return $this->mutateSellable(
            $inventory,
            InventoryMovementType::Return,
            abs($qty),
            $actor,
            'Sellable return restock',
            $referenceType,
            $referenceId,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * Intake units directly into the damaged bucket (e.g. POS damaged return).
     * Does not change sellable on_hand.
     */
    public function recordDamagedIntake(
        VariantInventory $inventory,
        int $qty,
        string $reason,
        ?Admin $actor = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $idempotencyKey = null,
    ): InventoryStockMovement {
        if ($qty < 1) {
            throw ValidationException::withMessages(['quantity' => ['Quantity must be at least 1.']]);
        }

        $movement = $this->mutateSellable(
            $inventory,
            InventoryMovementType::Damage,
            0,
            $actor,
            $reason,
            $referenceType,
            $referenceId,
            ['intake' => true],
            damagedDelta: $qty,
            idempotencyKey: $idempotencyKey,
        );
        if ($actor) {
            event(InventoryControlAudit::damaged($movement, $actor));
        }

        return $movement;
    }

    /**
     * Move sellable units into damaged bucket (cannot be sold).
     */
    public function markDamaged(
        VariantInventory $inventory,
        int $qty,
        string $reason,
        Admin $actor,
        ?string $referenceType = null,
        ?string $referenceId = null,
    ): InventoryStockMovement {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => ['Reason is required for damaged stock.']]);
        }
        if ($qty < 1) {
            throw ValidationException::withMessages(['quantity' => ['Quantity must be at least 1.']]);
        }

        $movement = $this->mutateSellable(
            $inventory,
            InventoryMovementType::Damage,
            -1 * $qty,
            $actor,
            $reason,
            $referenceType,
            $referenceId,
            null,
            damagedDelta: $qty,
        );
        event(InventoryControlAudit::damaged($movement, $actor));

        return $movement;
    }

    /**
     * Controlled adjustment to sellable stock. Reason required.
     *
     * @param  'adjustment'|'correction'|'damage'|'found'  $kind
     */
    public function adjust(
        Store $store,
        string $productVariantId,
        int $quantityChange,
        string $reason,
        Admin $actor,
        string $kind = 'adjustment',
    ): InventoryStockMovement {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => ['Reason is required for stock adjustments.']]);
        }
        if ($quantityChange === 0) {
            throw ValidationException::withMessages(['quantity_change' => ['Quantity change cannot be zero.']]);
        }

        $location = $this->stores->defaultLocation($store);
        $variant = ProductVariant::query()->findOrFail($productVariantId);

        return DB::transaction(function () use ($location, $variant, $quantityChange, $reason, $actor, $kind) {
            $inventory = $this->resolveOrCreateInventory($variant, $location, true);

            if ($kind === 'damage') {
                return $this->markDamaged($inventory, abs($quantityChange), $reason, $actor);
            }

            $type = $kind === 'correction' || $kind === 'found'
                ? InventoryMovementType::Correction
                : InventoryMovementType::Adjustment;

            $movement = $this->mutateSellable(
                $inventory,
                $type,
                $quantityChange,
                $actor,
                $reason,
            );
            event(InventoryControlAudit::adjusted($movement, $actor));

            return $movement;
        });
    }

    /**
     * @param  array{scope?: string, category_id?: string|null, variant_ids?: list<string>, notes?: string|null}  $data
     */
    public function createCountSession(Store $store, array $data, Admin $actor): InventoryCountSession
    {
        $location = $this->stores->defaultLocation($store);
        $scope = InventoryCountScope::tryFrom((string) ($data['scope'] ?? 'full')) ?? InventoryCountScope::Full;

        return DB::transaction(function () use ($store, $location, $scope, $data, $actor) {
            $session = InventoryCountSession::query()->create([
                'count_number' => $this->nextCountNumber($store),
                'store_id' => $store->id,
                'inventory_location_id' => $location->id,
                'scope' => $scope,
                'category_id' => $data['category_id'] ?? null,
                'status' => InventoryCountStatus::Counting,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
                'started_at' => now(),
            ]);

            $inventories = VariantInventory::query()
                ->where(function ($q) use ($location) {
                    $q->where('inventory_location_id', $location->id)
                        ->orWhere('warehouse_code', $location->code);
                })
                ->where('is_active', true)
                ->with('variant.product')
                ->get();

            if ($scope === InventoryCountScope::Category && ! empty($data['category_id'])) {
                $inventories = $inventories->filter(
                    fn (VariantInventory $inv) => $inv->variant?->product?->category_id === $data['category_id']
                );
            }

            if ($scope === InventoryCountScope::Selected && ! empty($data['variant_ids'])) {
                $ids = collect($data['variant_ids'])->all();
                $inventories = $inventories->filter(
                    fn (VariantInventory $inv) => in_array($inv->product_variant_id, $ids, true)
                );
            }

            foreach ($inventories as $inv) {
                InventoryCountLine::query()->create([
                    'inventory_count_session_id' => $session->id,
                    'product_variant_id' => $inv->product_variant_id,
                    'variant_inventory_id' => $inv->id,
                    'system_quantity' => (int) $inv->on_hand,
                ]);
            }

            return $session->fresh(['lines.variant.product', 'store', 'location']) ?? $session;
        });
    }

    /**
     * @param  list<array{line_id: string, counted_quantity: int, reason?: string|null}>  $lines
     */
    public function recordCountLines(InventoryCountSession $session, array $lines): InventoryCountSession
    {
        if (! in_array($session->status, [InventoryCountStatus::Draft, InventoryCountStatus::Counting], true)) {
            throw ValidationException::withMessages([
                'session' => ['Count session is not open for counting.'],
            ]);
        }

        return DB::transaction(function () use ($session, $lines) {
            foreach ($lines as $index => $row) {
                $line = InventoryCountLine::query()
                    ->where('inventory_count_session_id', $session->id)
                    ->whereKey($row['line_id'])
                    ->first();
                if ($line === null) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.line_id" => ['Count line not found.'],
                    ]);
                }
                $counted = (int) $row['counted_quantity'];
                if ($counted < 0) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.counted_quantity" => ['Counted quantity cannot be negative.'],
                    ]);
                }
                $diff = $counted - (int) $line->system_quantity;
                $line->forceFill([
                    'counted_quantity' => $counted,
                    'difference' => $diff,
                    'reason' => $row['reason'] ?? $line->reason,
                ])->save();
            }

            $session->forceFill(['status' => InventoryCountStatus::Counting])->save();

            return $session->fresh(['lines.variant.product']) ?? $session;
        });
    }

    public function submitCount(InventoryCountSession $session): InventoryCountSession
    {
        $uncounted = $session->lines()->whereNull('counted_quantity')->count();
        if ($uncounted > 0) {
            throw ValidationException::withMessages([
                'session' => ["{$uncounted} line(s) still need a counted quantity."],
            ]);
        }

        $session->forceFill([
            'status' => InventoryCountStatus::PendingApproval,
            'submitted_at' => now(),
        ])->save();

        return $session->fresh(['lines']) ?? $session;
    }

    public function approveCount(InventoryCountSession $session, Admin $approver, ?string $defaultReason = null): InventoryCountSession
    {
        if ($session->status !== InventoryCountStatus::PendingApproval) {
            throw ValidationException::withMessages([
                'session' => ['Only submitted counts can be approved.'],
            ]);
        }

        return DB::transaction(function () use ($session, $approver, $defaultReason) {
            /** @var InventoryCountSession $locked */
            $locked = InventoryCountSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $locked->load('lines');

            foreach ($locked->lines as $line) {
                if ($line->difference === null || (int) $line->difference === 0 || $line->is_adjusted) {
                    continue;
                }
                $reason = trim((string) ($line->reason ?: $defaultReason));
                if ($reason === '') {
                    throw ValidationException::withMessages([
                        'reason' => ["Line {$line->id} requires a reason for variance approval."],
                    ]);
                }

                $inventory = VariantInventory::query()->whereKey($line->variant_inventory_id)->lockForUpdate()->first();
                if ($inventory === null) {
                    continue;
                }

                $this->mutateSellable(
                    $inventory,
                    InventoryMovementType::Adjustment,
                    (int) $line->difference,
                    $approver,
                    $reason,
                    InventoryCountSession::class,
                    $locked->id,
                    ['count_line_id' => $line->id],
                );
                $line->forceFill(['is_adjusted' => true])->save();
            }

            $locked->forceFill([
                'status' => InventoryCountStatus::Approved,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ])->save();

            event(InventoryControlAudit::countCompleted($locked->fresh() ?? $locked, $approver));

            return $locked->fresh(['lines.variant.product', 'store']) ?? $locked;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(array $storeIds): array
    {
        $q = VariantInventory::query()->where('is_active', true);
        if ($storeIds !== []) {
            $q->whereHas('inventoryLocation', fn ($l) => $l->whereIn('store_id', $storeIds));
        }

        $rows = $q->with(['variant.product', 'inventoryLocation'])->get();
        $sellable = (int) $rows->sum('on_hand');
        $damaged = (int) $rows->sum('damaged');
        $low = $rows->filter(fn (VariantInventory $i) => $i->needsReorder())->count();
        $value = $rows->sum(function (VariantInventory $i) {
            $cost = (float) ($i->variant?->product?->cost_price ?? $i->variant?->product?->price ?? 0);

            return $i->physicalQuantity() * $cost;
        });

        return [
            'sku_count' => $rows->count(),
            'sellable_units' => $sellable,
            'damaged_units' => $damaged,
            'inspection_units' => (int) $rows->sum('inspection'),
            'low_stock_skus' => $low,
            'inventory_value' => round((float) $value, 2),
            'open_counts' => InventoryCountSession::query()
                ->when($storeIds !== [], fn ($c) => $c->whereIn('store_id', $storeIds))
                ->whereIn('status', [
                    InventoryCountStatus::Counting->value,
                    InventoryCountStatus::PendingApproval->value,
                ])
                ->count(),
        ];
    }

    /**
     * @return array{summary: array<string, mixed>, rows: list<array<string, mixed>>}
     */
    public function valuation(array $storeIds): array
    {
        $q = VariantInventory::query()
            ->where('is_active', true)
            ->with(['variant.product', 'inventoryLocation.store']);

        if ($storeIds !== []) {
            $q->whereHas('inventoryLocation', fn ($l) => $l->whereIn('store_id', $storeIds));
        }

        $rows = $q->get()->map(function (VariantInventory $inv) {
            $product = $inv->variant?->product;
            $unitCost = (float) ($product?->cost_price ?? $product?->price ?? 0);
            $qty = $inv->physicalQuantity();
            $sellable = (int) $inv->on_hand;

            return [
                'variant_inventory_id' => $inv->id,
                'product_variant_id' => $inv->product_variant_id,
                'sku' => $inv->variant?->sku,
                'product_name' => $product?->name,
                'store_id' => $inv->inventoryLocation?->store_id,
                'store_name' => $inv->inventoryLocation?->store?->name,
                'stock_quantity' => $qty,
                'sellable_quantity' => $sellable,
                'damaged_quantity' => (int) $inv->damaged,
                'unit_cost' => round($unitCost, 2),
                'cost_value' => round($qty * $unitCost, 2),
                'store_value' => round($sellable * $unitCost, 2),
                'product_value' => round($qty * $unitCost, 2),
            ];
        })->values()->all();

        return [
            'summary' => [
                'sku_count' => count($rows),
                'total_units' => (int) array_sum(array_column($rows, 'stock_quantity')),
                'total_cost_value' => round((float) array_sum(array_column($rows, 'cost_value')), 2),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lowStock(array $storeIds): array
    {
        $q = VariantInventory::query()
            ->where('is_active', true)
            ->with(['variant.product', 'inventoryLocation.store']);

        if ($storeIds !== []) {
            $q->whereHas('inventoryLocation', fn ($l) => $l->whereIn('store_id', $storeIds));
        }

        return $q->get()
            ->filter(fn (VariantInventory $i) => $i->needsReorder())
            ->map(fn (VariantInventory $inv) => [
                'variant_inventory_id' => $inv->id,
                'product_variant_id' => $inv->product_variant_id,
                'sku' => $inv->variant?->sku,
                'product_name' => $inv->variant?->product?->name,
                'store_name' => $inv->inventoryLocation?->store?->name,
                'available' => $inv->available(),
                'reorder_level' => (int) $inv->reorder_level,
                'status' => $inv->available() <= 0 ? 'out_of_stock' : 'low_stock',
            ])
            ->values()
            ->all();
    }

    public function locationForProduct(Product $product): ?InventoryLocation
    {
        if ($product->store_id === null) {
            return null;
        }
        $store = Store::query()->find($product->store_id);
        if ($store === null) {
            return null;
        }

        return $this->stores->defaultLocation($store);
    }

    private function nextCountNumber(Store $store): string
    {
        $prefix = 'CNT-'.strtoupper(preg_replace('/[^A-Z0-9]/i', '', $store->code) ?: 'ST').'-'.now()->format('Y').'-';
        $latest = InventoryCountSession::query()
            ->where('count_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('count_number')
            ->value('count_number');
        $next = $latest ? ((int) Str::afterLast($latest, '-')) + 1 : 1;

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}

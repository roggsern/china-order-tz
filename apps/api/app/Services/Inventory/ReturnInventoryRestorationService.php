<?php

namespace App\Services\Inventory;

use App\Enums\InventoryDisposition;
use App\Enums\InventoryMutationKind;
use App\Enums\ReturnItemResolution;
use App\Models\Admin;
use App\Models\Inventory;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnItem;
use App\Models\ReturnRequest;
use App\Models\VariantInventory;
use Illuminate\Validation\ValidationException;

/**
 * ADR-055 Phase F / RC1-G3 — Online return inventory restoration at Completed.
 *
 * Location rule (StockResolver):
 * 1. Resolve via StockResolver for the order line product/variant (canonical MAIN for online).
 * 2. Fall back to the simple inventory row when no variant MAIN stock exists.
 *
 * Idempotency: one MutationGate operation per ReturnItem (`return-restock:{return_item_id}`).
 */
class ReturnInventoryRestorationService
{
    public function __construct(
        private readonly InventoryMutationGate $gate,
        private readonly StockResolver $stockResolver,
    ) {}

    /**
     * Require each restock-eligible line to have a finalized disposition before Completed.
     */
    public function assertItemsReadyForCompletion(ReturnRequest $return): void
    {
        $return->loadMissing('items');

        foreach ($return->items as $index => $item) {
            if (! $item instanceof ReturnItem) {
                continue;
            }

            if ($item->resolution === ReturnItemResolution::Reject) {
                continue;
            }

            $disposition = $item->inventory_disposition;
            if (! $disposition instanceof InventoryDisposition || ! $disposition->isFinalizedForCompletion()) {
                throw ValidationException::withMessages([
                    "items.{$index}.inventory_disposition" => [
                        'A finalized inventory disposition (sellable, damaged, or no_restock) is required before completing this return item.',
                    ],
                ]);
            }
        }
    }

    public function restoreForCompletedReturn(ReturnRequest $return, ?Admin $actor = null): void
    {
        $return->loadMissing(['items.orderItem.product', 'items.orderItem.variant', 'order']);

        foreach ($return->items as $item) {
            if (! $item instanceof ReturnItem) {
                continue;
            }

            $this->restoreItem($return, $item, $actor);
        }
    }

    private function restoreItem(ReturnRequest $return, ReturnItem $item, ?Admin $actor): void
    {
        if ($item->resolution === ReturnItemResolution::Reject) {
            return;
        }

        $disposition = $item->inventory_disposition;
        if (! $disposition instanceof InventoryDisposition) {
            return;
        }

        if ($disposition === InventoryDisposition::NoRestock
            || $disposition === InventoryDisposition::Inspection
            || $disposition === InventoryDisposition::InspectionHold) {
            return;
        }

        $qty = max(0, (int) $item->quantity);
        if ($qty < 1) {
            return;
        }

        $orderItem = $item->orderItem ?? OrderItem::query()->find($item->order_item_id);
        if ($orderItem === null) {
            return;
        }

        $idempotencyKey = 'return-restock:'.$item->id;
        $metadata = [
            'source' => 'online_return_restock',
            'return_request_id' => $return->id,
            'return_item_id' => $item->id,
            'order_id' => $return->order_id,
            'order_item_id' => $orderItem->id,
            'disposition' => $disposition->value,
            'idempotency_key' => $idempotencyKey,
        ];

        $reason = sprintf(
            'Return %s item %s — %s',
            $return->id,
            $item->id,
            $disposition->value,
        );

        if ($disposition->restocksSellable()) {
            $this->applySellableRestock($orderItem, $qty, $reason, $idempotencyKey, $metadata, $actor, $item);

            return;
        }

        if ($disposition->recordsDamagedIntake()) {
            $this->applyDamagedIntake($orderItem, $qty, $reason, $idempotencyKey, $metadata, $actor, $item);
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function applySellableRestock(
        OrderItem $orderItem,
        int $qty,
        string $reason,
        string $idempotencyKey,
        array $metadata,
        ?Admin $actor,
        ReturnItem $returnItem,
    ): void {
        if (filled($orderItem->product_variant_id)) {
            $product = $orderItem->product ?? Product::query()->find($orderItem->product_id);
            $variant = $orderItem->variant ?? ProductVariant::query()->find($orderItem->product_variant_id);
            if ($variant === null) {
                return;
            }

            $stock = $this->stockResolver->resolveVariantProduct($variant, null, $product);
            if ($stock->resolved && $stock->inventory instanceof VariantInventory) {
                $this->gate->mutateVariantSellable(
                    inventory: $stock->inventory,
                    kind: InventoryMutationKind::Return,
                    quantityChange: $qty,
                    actor: $actor,
                    reason: $reason,
                    referenceType: ReturnItem::class,
                    referenceId: $returnItem->id,
                    metadata: $metadata,
                    idempotencyKey: $idempotencyKey,
                );

                return;
            }

            $legacy = Inventory::query()
                ->where('product_id', $orderItem->product_id)
                ->where('product_variant_id', $orderItem->product_variant_id)
                ->lockForUpdate()
                ->first();

            if ($legacy === null) {
                return;
            }

            $this->gate->mutateSimple(
                inventory: $legacy,
                kind: InventoryMutationKind::Return,
                quantityChange: $qty,
                reason: $idempotencyKey,
                idempotencyKey: $idempotencyKey,
            );

            return;
        }

        $product = $orderItem->product ?? Product::query()->find($orderItem->product_id);
        if ($product === null) {
            return;
        }

        $stock = $this->stockResolver->resolveSimpleProduct($product);
        if (! $stock->resolved || ! ($stock->inventory instanceof Inventory)) {
            return;
        }

        $this->gate->mutateSimple(
            inventory: $stock->inventory,
            kind: InventoryMutationKind::Return,
            quantityChange: $qty,
            reason: $idempotencyKey,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function applyDamagedIntake(
        OrderItem $orderItem,
        int $qty,
        string $reason,
        string $idempotencyKey,
        array $metadata,
        ?Admin $actor,
        ReturnItem $returnItem,
    ): void {
        if (! filled($orderItem->product_variant_id)) {
            return;
        }

        $product = $orderItem->product ?? Product::query()->find($orderItem->product_id);
        $variant = $orderItem->variant ?? ProductVariant::query()->find($orderItem->product_variant_id);
        if ($variant === null) {
            return;
        }

        $stock = $this->stockResolver->resolveVariantProduct($variant, null, $product);
        if (! $stock->resolved || ! ($stock->inventory instanceof VariantInventory)) {
            return;
        }

        $this->gate->mutateVariantSellable(
            inventory: $stock->inventory,
            kind: InventoryMutationKind::Damage,
            quantityChange: 0,
            actor: $actor,
            reason: $reason,
            referenceType: ReturnItem::class,
            referenceId: $returnItem->id,
            metadata: array_merge($metadata, ['intake' => true]),
            damagedDelta: $qty,
            idempotencyKey: $idempotencyKey,
        );
    }
}

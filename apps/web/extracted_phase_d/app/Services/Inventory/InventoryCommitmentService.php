<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMutationKind;
use App\Models\CheckoutSession;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
use App\Services\Inventory\DTOs\InventoryCommitmentContext;
use App\Services\Inventory\DTOs\InventoryCommitmentResult;
use App\Services\Inventory\DTOs\InventoryMutationResult;
use App\Services\Inventory\DTOs\ReservationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Canonical inventory commitment after successful payment (ADR 055 / Phase 2A-3B-3 / 2A-3C-2).
 *
 * Prefer atomic reservation → sale conversion when checkout holds remain;
 * otherwise direct SELL via InventoryMutationGate.
 */
final class InventoryCommitmentService
{
    public function __construct(
        private readonly InventoryMutationGate $mutationGate,
        private readonly StockResolver $stockResolver,
        private readonly ReservationService $reservationService,
    ) {}

    public function commitForOrder(InventoryCommitmentContext $context): InventoryCommitmentResult
    {
        return DB::transaction(function () use ($context): InventoryCommitmentResult {
            $order = $context->order;
            $order->loadMissing(['items.product', 'items.variant', 'checkoutSession']);

            if ($this->reservationService->hasConvertibleHolds($order)) {
                return $this->commitViaReservationConvert($order, $context);
            }

            return $this->commitViaDirectSell($order, $context);
        });
    }

    private function commitViaReservationConvert(
        Order $order,
        InventoryCommitmentContext $context,
    ): InventoryCommitmentResult {
        $session = $order->checkoutSession
            ?? ($order->checkout_session_id
                ? CheckoutSession::query()->find($order->checkout_session_id)
                : null);

        try {
            $converted = $this->reservationService->convertToCommit(new ReservationContext(
                checkoutSession: $session,
                cart: $session?->cart,
                order: $order,
                source: $context->source,
                metadata: array_merge($context->metadata, [
                    'payment_transaction_id' => $context->payment?->id,
                    'from_reservation' => true,
                ]),
                convertToCommitment: true,
            ));
        } catch (ValidationException $e) {
            if (! $context->strict) {
                Log::warning('inventory.commitment_convert_soft_skip', [
                    'order_id' => $order->id,
                    'errors' => $e->errors(),
                    'source' => $context->source,
                ]);

                return new InventoryCommitmentResult(
                    committed: true,
                    order: $order,
                    itemsCommitted: 0,
                    itemsSkippedIdempotent: 0,
                    itemResults: [],
                    meta: [
                        'source' => $context->source,
                        'payment_transaction_id' => $context->payment?->id,
                        'from_reservation' => true,
                        'soft_skipped' => true,
                    ],
                );
            }

            throw $e;
        }

        return new InventoryCommitmentResult(
            committed: true,
            order: $order,
            itemsCommitted: $converted->linesAffected,
            itemsSkippedIdempotent: $converted->linesIdempotent,
            itemResults: $converted->lineResults,
            meta: [
                'source' => $context->source,
                'payment_transaction_id' => $context->payment?->id,
                'channel' => $context->channel,
                'from_reservation' => true,
                'warehouse_allocation' => $context->warehouseCode,
                'convert_operation' => $converted->operation,
            ],
        );
    }

    private function commitViaDirectSell(
        Order $order,
        InventoryCommitmentContext $context,
    ): InventoryCommitmentResult {
        $items = $context->orderItems ?? $order->items;
        $itemResults = [];
        $committed = 0;
        $skippedIdempotent = 0;

        foreach ($items as $item) {
            if (! $item instanceof OrderItem) {
                continue;
            }

            if ((int) $item->quantity < 1) {
                continue;
            }

            try {
                $result = $this->commitOrderItem($order, $item, $context);
            } catch (ValidationException $e) {
                if (! $context->strict) {
                    Log::warning('inventory.commitment_soft_skip', [
                        'order_id' => $order->id,
                        'order_item_id' => $item->id,
                        'errors' => $e->errors(),
                        'source' => $context->source,
                    ]);

                    continue;
                }

                throw $e;
            }

            $itemResults[] = $result;
            if ($result->idempotentReplay) {
                $skippedIdempotent++;
            } else {
                $committed++;
            }
        }

        return new InventoryCommitmentResult(
            committed: true,
            order: $order,
            itemsCommitted: $committed,
            itemsSkippedIdempotent: $skippedIdempotent,
            itemResults: $itemResults,
            meta: [
                'source' => $context->source,
                'payment_transaction_id' => $context->payment?->id,
                'channel' => $context->channel,
                'from_reservation' => false,
                'warehouse_allocation' => $context->warehouseCode,
            ],
        );
    }

    private function commitOrderItem(
        Order $order,
        OrderItem $item,
        InventoryCommitmentContext $context,
    ): InventoryMutationResult {
        $qty = (int) $item->quantity;
        $idempotencyKey = $this->idempotencyKeyForItem($item);
        $reason = "Order {$order->order_number} inventory commit";
        $metadata = array_merge($context->metadata, [
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'source' => $context->source,
            'commitment' => true,
        ]);

        try {
            if (filled($item->product_variant_id)) {
                return $this->commitVariantLine(
                    item: $item,
                    qty: $qty,
                    reason: $reason,
                    idempotencyKey: $idempotencyKey,
                    metadata: $metadata,
                    context: $context,
                );
            }

            return $this->commitSimpleLine(
                item: $item,
                qty: $qty,
                idempotencyKey: $idempotencyKey,
            );
        } catch (ValidationException) {
            throw ValidationException::withMessages([
                'order' => ["Insufficient stock for {$item->product_name}."],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function commitVariantLine(
        OrderItem $item,
        int $qty,
        string $reason,
        string $idempotencyKey,
        array $metadata,
        InventoryCommitmentContext $context,
    ): InventoryMutationResult {
        $product = $item->product ?? Product::query()->find($item->product_id);
        $variant = $item->variant ?? ProductVariant::query()->find($item->product_variant_id);

        if ($variant === null) {
            throw ValidationException::withMessages([
                'quantity' => ['Variant missing for order item.'],
            ]);
        }

        $stock = $this->stockResolver->resolveVariantProduct($variant, null, $product);
        if ($stock->resolved && $stock->inventory instanceof VariantInventory) {
            return $this->mutationGate->mutateVariantSellable(
                inventory: $stock->inventory,
                kind: InventoryMutationKind::Sell,
                quantityChange: -1 * $qty,
                actor: $context->actor,
                reason: $reason,
                referenceType: OrderItem::class,
                referenceId: $item->id,
                metadata: $metadata,
                idempotencyKey: $idempotencyKey,
            );
        }

        $legacy = Inventory::query()
            ->where('product_id', $item->product_id)
            ->where('product_variant_id', $item->product_variant_id)
            ->lockForUpdate()
            ->first();

        if ($legacy === null) {
            throw ValidationException::withMessages([
                'quantity' => ['No inventory row for variant line.'],
            ]);
        }

        return $this->mutationGate->mutateSimple(
            inventory: $legacy,
            kind: InventoryMutationKind::Sell,
            quantityChange: -1 * $qty,
            reason: $idempotencyKey,
            idempotencyKey: $idempotencyKey,
        );
    }

    private function commitSimpleLine(
        OrderItem $item,
        int $qty,
        string $idempotencyKey,
    ): InventoryMutationResult {
        $product = $item->product ?? Product::query()->find($item->product_id);
        if ($product === null) {
            throw ValidationException::withMessages([
                'quantity' => ['Product missing for order item.'],
            ]);
        }

        $stock = $this->stockResolver->resolveSimpleProduct($product);
        if (! $stock->resolved || ! $stock->inventory instanceof Inventory) {
            throw ValidationException::withMessages([
                'quantity' => ['No inventory row for simple line.'],
            ]);
        }

        return $this->mutationGate->mutateSimple(
            inventory: $stock->inventory,
            kind: InventoryMutationKind::Sell,
            quantityChange: -1 * $qty,
            reason: $idempotencyKey,
            idempotencyKey: $idempotencyKey,
        );
    }

    private function idempotencyKeyForItem(OrderItem $item): string
    {
        return 'inventory-commit:'.$item->id;
    }
}

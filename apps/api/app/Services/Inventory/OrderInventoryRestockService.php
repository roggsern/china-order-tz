<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMutationKind;
use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\CheckoutSession;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
use App\Services\Inventory\DTOs\ReservationContext;
use Illuminate\Support\Facades\DB;

/**
 * ADR-055 Phase 2A-3C-3 — cancel / refund inventory restores via InventoryMutationGate.
 *
 * Paid cancel → Return (restock on_hand / quantity).
 * Unpaid cancel with checkout holds → Release (reserved → available).
 */
final class OrderInventoryRestockService
{
    public function __construct(
        private readonly InventoryMutationGate $gate,
        private readonly StockResolver $stockResolver,
        private readonly ReservationService $reservationService,
    ) {}

    /**
     * Restock committed inventory for a cancelled paid order (simple + variant).
     * Idempotent per order item via MutationGate keys.
     */
    public function restockCancelledOrder(Order $order, ?Admin $actor = null): void
    {
        DB::transaction(function () use ($order, $actor): void {
            $order->loadMissing(['items.product', 'items.variant']);

            foreach ($order->items as $item) {
                if (! $item instanceof OrderItem) {
                    continue;
                }
                $this->restockOrderItem($order, $item, $actor);
            }
        });
    }

    /**
     * Release checkout reservation holds when an unpaid order is cancelled / abandoned.
     */
    public function releaseCheckoutHoldsIfPresent(Order $order, ?Admin $actor = null): void
    {
        if (! $this->reservationService->hasConvertibleHolds($order)) {
            return;
        }

        $session = $order->checkoutSession
            ?? (filled($order->checkout_session_id)
                ? CheckoutSession::query()->find($order->checkout_session_id)
                : null);

        if ($session === null) {
            return;
        }

        $this->reservationService->release(new ReservationContext(
            checkoutSession: $session,
            cart: $session->cart,
            order: $order,
            source: 'order_cancel',
            metadata: [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'actor_admin_id' => $actor?->id,
            ],
        ));
    }

    /**
     * Apply the correct inventory side-effect after lifecycle cancel based on prior status.
     */
    public function applyAfterCancel(Order $order, OrderStatus $priorStatus, ?Admin $actor = null): void
    {
        if (in_array($priorStatus, [OrderStatus::Paid, OrderStatus::Confirmed, OrderStatus::Processing], true)) {
            $this->restockCancelledOrder($order, $actor);

            return;
        }

        if (in_array($priorStatus, [OrderStatus::PendingPayment, OrderStatus::Pending], true)) {
            $this->releaseCheckoutHoldsIfPresent($order, $actor);
        }
    }

    private function restockOrderItem(Order $order, OrderItem $item, ?Admin $actor): void
    {
        $qty = max(0, (int) $item->quantity);
        if ($qty <= 0) {
            return;
        }

        $idempotencyKey = 'inventory-cancel-restock:'.$item->id;
        $metadata = [
            'source' => 'order_cancel_restock',
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'order_number' => $order->order_number,
            'idempotency_key' => $idempotencyKey,
        ];
        $reason = 'Order '.$order->order_number.' cancelled — restock';

        if (filled($item->product_variant_id)) {
            $this->restockVariantLine($item, $qty, $reason, $idempotencyKey, $metadata, $actor);

            return;
        }

        $this->restockSimpleLine($item, $qty, $idempotencyKey);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function restockVariantLine(
        OrderItem $item,
        int $qty,
        string $reason,
        string $idempotencyKey,
        array $metadata,
        ?Admin $actor,
    ): void {
        $product = $item->product ?? Product::query()->find($item->product_id);
        $variant = $item->variant ?? ProductVariant::query()->find($item->product_variant_id);

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
                referenceType: OrderItem::class,
                referenceId: $item->id,
                metadata: $metadata,
                idempotencyKey: $idempotencyKey,
            );

            return;
        }

        // Legacy variant row on inventory table (no MAIN variant_inventories).
        $legacy = Inventory::query()
            ->where('product_id', $item->product_id)
            ->where('product_variant_id', $item->product_variant_id)
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
    }

    private function restockSimpleLine(OrderItem $item, int $qty, string $idempotencyKey): void
    {
        $product = $item->product ?? Product::query()->find($item->product_id);
        if ($product === null) {
            return;
        }

        $stock = $this->stockResolver->resolveSimpleProduct($product);
        if (! $stock->resolved || ! $stock->inventory instanceof Inventory) {
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
}

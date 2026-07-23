<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMutationKind;
use App\Enums\PurchasabilityPath;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CheckoutSession;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\InventoryStockMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
use App\Services\Inventory\DTOs\InventoryMutationResult;
use App\Services\Inventory\DTOs\ReservationContext;
use App\Services\Inventory\DTOs\ReservationResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Checkout reservation lifecycle (ADR 055 / Phase 2A-3B-4 / 2A-3C-2).
 *
 * Soft-holds Available stock via reserved columns through InventoryMutationGate.
 * convertToCommit() atomically consumes reserved and decrements on_hand (sale ledger).
 */
final class ReservationService
{
    public function __construct(
        private readonly InventoryMutationGate $mutationGate,
        private readonly StockResolver $stockResolver,
    ) {}

    /**
     * Reserve stock for all lines on a checkout session's cart.
     */
    public function reserve(ReservationContext $context): ReservationResult
    {
        $session = $context->checkoutSession;
        $cart = $context->cart ?? $session?->cart;

        if ($session === null || $cart === null) {
            throw ValidationException::withMessages([
                'reservation' => ['Checkout session and cart are required to reserve.'],
            ]);
        }

        $cart->loadMissing(['items.product', 'items.variant']);

        return DB::transaction(function () use ($session, $cart, $context) {
            $results = [];
            $affected = 0;
            $idempotent = 0;

            foreach ($cart->items as $item) {
                if (! $item instanceof CartItem || (int) $item->quantity < 1) {
                    continue;
                }

                $result = $this->reserveLine($session, $item, $context);
                $results[] = $result;
                if ($result->idempotentReplay) {
                    $idempotent++;
                } else {
                    $affected++;
                }
            }

            return new ReservationResult(
                ok: true,
                operation: 'reserve',
                linesAffected: $affected,
                linesIdempotent: $idempotent,
                lineResults: $results,
                meta: [
                    'checkout_session_id' => $session->id,
                    'cart_id' => $cart->id,
                    'source' => $context->source,
                    'expires_at' => $context->expiresAt?->toIso8601String() ?? $session->expires_at?->toIso8601String(),
                ],
            );
        });
    }

    /**
     * Release all soft-holds for a checkout session (cancel / complete / replace).
     */
    public function release(ReservationContext $context): ReservationResult
    {
        $session = $context->checkoutSession;
        if ($session === null) {
            throw ValidationException::withMessages([
                'reservation' => ['Checkout session is required to release.'],
            ]);
        }

        $cart = $context->cart ?? $session->cart;
        if ($cart === null) {
            return new ReservationResult(
                ok: true,
                operation: 'release',
                linesAffected: 0,
                linesIdempotent: 0,
                meta: ['checkout_session_id' => $session->id, 'note' => 'No cart; nothing to release'],
            );
        }

        $cart->loadMissing(['items.product', 'items.variant']);

        return DB::transaction(function () use ($session, $cart, $context) {
            $results = [];
            $affected = 0;
            $idempotent = 0;

            foreach ($cart->items as $item) {
                if (! $item instanceof CartItem || (int) $item->quantity < 1) {
                    continue;
                }

                $result = $this->releaseLine($session, $item, $context);
                $results[] = $result;
                if ($result->idempotentReplay) {
                    $idempotent++;
                } else {
                    $affected++;
                }
            }

            return new ReservationResult(
                ok: true,
                operation: 'release',
                linesAffected: $affected,
                linesIdempotent: $idempotent,
                lineResults: $results,
                meta: [
                    'checkout_session_id' => $session->id,
                    'cart_id' => $cart->id,
                    'source' => $context->source,
                ],
            );
        });
    }

    /**
     * Expire = release holds for a timed-out checkout session.
     */
    public function expire(ReservationContext $context): ReservationResult
    {
        $released = $this->release(new ReservationContext(
            checkoutSession: $context->checkoutSession,
            cart: $context->cart,
            source: $context->source !== 'checkout' ? $context->source : 'checkout_expire',
            metadata: array_merge($context->metadata, ['expired' => true]),
        ));

        return new ReservationResult(
            ok: $released->ok,
            operation: 'expire',
            linesAffected: $released->linesAffected,
            linesIdempotent: $released->linesIdempotent,
            lineResults: $released->lineResults,
            meta: array_merge($released->meta, ['expired' => true]),
        );
    }

    /**
     * Extend reservation window (inventory holds unchanged; TTL owned by checkout session).
     */
    public function extend(ReservationContext $context): ReservationResult
    {
        $session = $context->checkoutSession;
        if ($session === null) {
            throw ValidationException::withMessages([
                'reservation' => ['Checkout session is required to extend.'],
            ]);
        }

        return new ReservationResult(
            ok: true,
            operation: 'extend',
            linesAffected: 0,
            linesIdempotent: 0,
            meta: [
                'checkout_session_id' => $session->id,
                'expires_at' => $context->expiresAt?->toIso8601String() ?? $session->expires_at?->toIso8601String(),
                'note' => 'Hold quantities unchanged; session TTL refreshed by checkout',
            ],
        );
    }

    /**
     * Convert open soft-holds into a committed sale for order lines (ADR 055 / 2A-3C-2).
     *
     * Atomically: reserved −= qty and on_hand −= qty via MutationGate.convertReservedToSale.
     * Idempotent on inventory-commit:{orderItemId} (shared with InventoryCommitmentService).
     */
    public function convertToCommit(ReservationContext $context): ReservationResult
    {
        $order = $context->order;
        if ($order === null) {
            throw ValidationException::withMessages([
                'reservation' => ['Order is required to convert a reservation.'],
            ]);
        }

        $session = $context->checkoutSession ?? $order->checkoutSession;
        $order->loadMissing(['items.product', 'items.variant']);

        return DB::transaction(function () use ($order, $session, $context) {
            $results = [];
            $affected = 0;
            $idempotent = 0;

            foreach ($order->items as $item) {
                if (! $item instanceof OrderItem || (int) $item->quantity < 1) {
                    continue;
                }

                $result = $this->convertOrderItem($order, $item, $context);
                $results[] = $result;
                if ($result->idempotentReplay) {
                    $idempotent++;
                } else {
                    $affected++;
                }
            }

            return new ReservationResult(
                ok: true,
                operation: 'convert_to_commit',
                linesAffected: $affected,
                linesIdempotent: $idempotent,
                lineResults: $results,
                meta: [
                    'checkout_session_id' => $session?->id,
                    'order_id' => $order->id,
                    'source' => $context->source,
                    'from_reservation' => true,
                ],
            );
        });
    }

    /**
     * True when the order's checkout session still has reserved qty covering at least one line.
     */
    public function hasConvertibleHolds(Order $order): bool
    {
        if (! filled($order->checkout_session_id)) {
            return false;
        }

        $order->loadMissing(['items.product', 'items.variant']);

        foreach ($order->items as $item) {
            if (! $item instanceof OrderItem || (int) $item->quantity < 1) {
                continue;
            }

            try {
                $inventory = $this->resolveInventoryRowForOrderItem($item);
            } catch (ValidationException) {
                continue;
            }

            $qty = (int) $item->quantity;
            if ($inventory instanceof VariantInventory && (int) $inventory->reserved >= $qty) {
                return true;
            }
            if ($inventory instanceof Inventory && (int) $inventory->reserved_quantity >= $qty) {
                return true;
            }
        }

        return false;
    }

    private function convertOrderItem(
        Order $order,
        OrderItem $item,
        ReservationContext $context,
    ): InventoryMutationResult {
        $inventory = $this->resolveInventoryRowForOrderItem($item);
        $qty = (int) $item->quantity;
        $key = 'inventory-commit:'.$item->id;

        return $this->mutationGate->convertReservedToSale(
            inventory: $inventory,
            quantity: $qty,
            reason: "Order {$order->order_number} reservation convert",
            referenceType: OrderItem::class,
            referenceId: $item->id,
            metadata: array_merge($context->metadata, [
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'reservation_convert' => true,
            ]),
            idempotencyKey: $key,
        );
    }

    private function resolveInventoryRowForOrderItem(OrderItem $item): Inventory|VariantInventory
    {
        if (filled($item->product_variant_id)) {
            $product = $item->product ?? Product::query()->find($item->product_id);
            $variant = $item->variant ?? ProductVariant::query()->find($item->product_variant_id);
            if ($variant === null) {
                throw ValidationException::withMessages([
                    'product_variant_id' => ['Variant not found for reservation convert.'],
                ]);
            }

            $stock = $this->stockResolver->resolveVariantProduct($variant, null, $product);
            if ($stock->resolved && $stock->inventory instanceof VariantInventory) {
                return $stock->inventory;
            }

            $legacy = Inventory::query()
                ->where('product_id', $item->product_id)
                ->where('product_variant_id', $item->product_variant_id)
                ->first();

            if ($legacy !== null) {
                return $legacy;
            }

            throw ValidationException::withMessages([
                'quantity' => ['No inventory available to convert for this variant.'],
            ]);
        }

        $product = $item->product ?? Product::query()->find($item->product_id);
        if ($product === null) {
            throw ValidationException::withMessages([
                'product_id' => ['Product not found for reservation convert.'],
            ]);
        }

        $stock = $this->stockResolver->resolveSimpleProduct($product);
        if (! $stock->resolved || ! $stock->inventory instanceof Inventory) {
            throw ValidationException::withMessages([
                'quantity' => ['No inventory available to convert for this product.'],
            ]);
        }

        return $stock->inventory;
    }

    /**
     * Sync holds to current cart: release previous session holds then reserve current lines.
     */
    public function syncForCheckout(CheckoutSession $session, Cart $cart): ReservationResult
    {
        return DB::transaction(function () use ($session, $cart) {
            $this->release(new ReservationContext(
                checkoutSession: $session,
                cart: $cart,
                source: 'checkout_sync_release',
            ));

            return $this->reserve(new ReservationContext(
                checkoutSession: $session,
                cart: $cart,
                expiresAt: $session->expires_at,
                source: 'checkout_sync_reserve',
            ));
        });
    }

    private function reserveLine(
        CheckoutSession $session,
        CartItem $item,
        ReservationContext $context,
    ): InventoryMutationResult {
        $inventory = $this->resolveInventoryRow($item);
        $qty = (int) $item->quantity;
        $key = $this->reserveKey($session, $item);

        return $this->mutationGate->mutateReserved(
            inventory: $inventory,
            kind: InventoryMutationKind::Reserve,
            quantity: $qty,
            reason: "Checkout reserve {$session->id}",
            referenceType: CheckoutSession::class,
            referenceId: $session->id,
            metadata: array_merge($context->metadata, [
                'cart_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'hold_qty' => $qty,
            ]),
            idempotencyKey: $key,
        );
    }

    private function releaseLine(
        CheckoutSession $session,
        CartItem $item,
        ReservationContext $context,
    ): InventoryMutationResult {
        $inventory = $this->resolveInventoryRow($item);
        $qty = $this->openHoldQuantity($session, $item) ?? (int) $item->quantity;
        $key = $this->releaseKey($session, $item);

        if ($qty < 1) {
            return new InventoryMutationResult(
                applied: true,
                kind: InventoryMutationKind::Release,
                path: $inventory instanceof VariantInventory
                    ? PurchasabilityPath::Variant
                    : PurchasabilityPath::Simple,
                source: $inventory instanceof VariantInventory ? 'variant_inventories' : 'inventory',
                quantityBefore: 0,
                quantityChange: 0,
                quantityAfter: 0,
                inventory: $inventory,
                movement: null,
                idempotentReplay: true,
                meta: ['soft_release' => true, 'note' => 'Nothing to release'],
            );
        }

        try {
            return $this->mutationGate->mutateReserved(
                inventory: $inventory,
                kind: InventoryMutationKind::Release,
                quantity: $qty,
                reason: "Checkout release {$session->id}",
                referenceType: CheckoutSession::class,
                referenceId: $session->id,
                metadata: array_merge($context->metadata, [
                    'cart_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'hold_qty' => $qty,
                ]),
                idempotencyKey: $key,
            );
        } catch (ValidationException $e) {
            $fresh = $inventory->fresh() ?? $inventory;
            $reserved = $fresh instanceof VariantInventory
                ? (int) $fresh->reserved
                : (int) $fresh->reserved_quantity;

            // Only soft-succeed when there is truly nothing held on the row.
            if ($reserved === 0 && isset($e->errors()['quantity'])) {
                return new InventoryMutationResult(
                    applied: true,
                    kind: InventoryMutationKind::Release,
                    path: $inventory instanceof VariantInventory
                        ? PurchasabilityPath::Variant
                        : PurchasabilityPath::Simple,
                    source: $inventory instanceof VariantInventory ? 'variant_inventories' : 'inventory',
                    quantityBefore: 0,
                    quantityChange: 0,
                    quantityAfter: 0,
                    inventory: $fresh,
                    movement: null,
                    idempotentReplay: true,
                    meta: ['soft_release' => true, 'note' => 'Nothing to release'],
                );
            }

            throw $e;
        }
    }

    /**
     * Quantity held by the open reserve cycle for this cart line (from ledger metadata).
     */
    private function openHoldQuantity(CheckoutSession $session, CartItem $item): ?int
    {
        $reserveCount = $this->countKeysWithPrefix($this->lineKeyPrefix('checkout-reserve', $session, $item));
        $releaseCount = $this->countKeysWithPrefix($this->lineKeyPrefix('checkout-release', $session, $item));

        if ($reserveCount <= $releaseCount) {
            return null;
        }

        $key = $this->lineKeyPrefix('checkout-reserve', $session, $item).':c'.$reserveCount;

        $variantMovement = InventoryStockMovement::query()
            ->where('reference_type', CheckoutSession::class)
            ->where('reference_id', $session->id)
            ->get()
            ->first(static fn (InventoryStockMovement $m): bool => ($m->metadata['idempotency_key'] ?? null) === $key);

        if ($variantMovement !== null) {
            $hold = $variantMovement->metadata['hold_qty'] ?? null;

            return is_numeric($hold) ? (int) $hold : null;
        }

        $simpleMovement = InventoryMovement::query()
            ->where('reason', 'like', $key.'%')
            ->first();

        if ($simpleMovement !== null) {
            return abs((int) $simpleMovement->quantity);
        }

        return null;
    }

    private function resolveInventoryRow(CartItem $item): Inventory|VariantInventory
    {
        $product = $item->product ?? Product::query()->find($item->product_id);

        if (filled($item->product_variant_id)) {
            $variant = $item->variant ?? ProductVariant::query()->find($item->product_variant_id);
            if ($variant === null) {
                throw ValidationException::withMessages([
                    'product_variant_id' => ['Variant not found for reservation.'],
                ]);
            }

            $stock = $this->stockResolver->resolveVariantProduct($variant, null, $product);
            if ($stock->resolved && $stock->inventory instanceof VariantInventory) {
                return $stock->inventory;
            }

            $legacy = Inventory::query()
                ->where('product_id', $item->product_id)
                ->where('product_variant_id', $item->product_variant_id)
                ->first();

            if ($legacy !== null) {
                return $legacy;
            }

            throw ValidationException::withMessages([
                'quantity' => ['No inventory available to reserve for this variant.'],
            ]);
        }

        if ($product === null) {
            throw ValidationException::withMessages([
                'product_id' => ['Product not found for reservation.'],
            ]);
        }

        $stock = $this->stockResolver->resolveSimpleProduct($product);
        if (! $stock->resolved || ! $stock->inventory instanceof Inventory) {
            throw ValidationException::withMessages([
                'quantity' => ['No inventory available to reserve for this product.'],
            ]);
        }

        return $stock->inventory;
    }

    private function reserveKey(CheckoutSession $session, CartItem $item): string
    {
        return $this->lineKeyPrefix('checkout-reserve', $session, $item).':c'.$this->nextReserveCycle($session, $item);
    }

    private function releaseKey(CheckoutSession $session, CartItem $item): string
    {
        return $this->lineKeyPrefix('checkout-release', $session, $item).':c'.$this->currentHoldCycle($session, $item);
    }

    private function lineKeyPrefix(string $op, CheckoutSession $session, CartItem $item): string
    {
        return $op.':'.$session->id.':'.$item->id;
    }

    /**
     * Next reserve cycle = prior releases for this line + 1 (allows re-reserve after release).
     */
    private function nextReserveCycle(CheckoutSession $session, CartItem $item): int
    {
        return $this->countKeysWithPrefix($this->lineKeyPrefix('checkout-release', $session, $item)) + 1;
    }

    /**
     * Release the open hold cycle, or replay the last release key when already released.
     */
    private function currentHoldCycle(CheckoutSession $session, CartItem $item): int
    {
        $reserveCount = $this->countKeysWithPrefix($this->lineKeyPrefix('checkout-reserve', $session, $item));
        $releaseCount = $this->countKeysWithPrefix($this->lineKeyPrefix('checkout-release', $session, $item));

        if ($reserveCount > $releaseCount) {
            return $reserveCount;
        }

        return max($releaseCount, 1);
    }

    private function countKeysWithPrefix(string $prefix): int
    {
        $variant = InventoryStockMovement::query()
            ->where('reference_type', CheckoutSession::class)
            ->get()
            ->filter(static function (InventoryStockMovement $m) use ($prefix): bool {
                $key = (string) ($m->metadata['idempotency_key'] ?? '');

                return str_starts_with($key, $prefix.':c');
            })
            ->count();

        $simple = InventoryMovement::query()
            ->where('reason', 'like', $prefix.':c%')
            ->count();

        return $variant + $simple;
    }
}

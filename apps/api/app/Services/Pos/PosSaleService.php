<?php

namespace App\Services\Pos;

use App\Enums\CartStatus;
use App\Enums\CommerceChannelCode;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PosPaymentHandler;
use App\Enums\SalesOrigin;
use App\Events\Audit\PaymentConfirmed;
use App\Events\Audit\StorePlatformAudit;
use App\Events\Commerce\CommerceOrderCreated;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethodDefinition;
use App\Models\PosReceipt;
use App\Models\PosSaleIdempotencyKey;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantInventory;
use App\Services\Commerce\CommerceChannelResolver;
use App\Services\CostProfit\ProfitEngine;
use App\Services\Orders\Lifecycle\OrderLifecycleContext;
use App\Services\Orders\Lifecycle\OrderLifecycleEngine;
use App\Services\Orders\OrderNumberGenerator;
use App\Services\Orders\OrderSnapshotEngine;
use App\Services\Promotions\DiscountResolver;
use App\Services\Promotions\PromotionUsageService;
use App\Services\Inventory\InventoryControlEngine;
use App\Services\Stores\ActiveStoreContext;
use App\Services\Stores\StoreService;
use App\Support\Pos\PosErrors;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * POS selling workflow — thin client over Product / Pricing / Promotion / Order / Inventory engines.
 */
class PosSaleService
{
    public function __construct(
        private readonly ActiveStoreContext $storeContext,
        private readonly StoreService $stores,
        private readonly CommerceChannelResolver $channels,
        private readonly OrderNumberGenerator $orderNumbers,
        private readonly OrderSnapshotEngine $snapshots,
        private readonly PosCatalogService $catalog,
        private readonly DiscountResolver $discounts,
        private readonly PromotionUsageService $promotionUsages,
        private readonly PosReceiptService $receipts,
        private readonly ProfitEngine $profits,
        private readonly PosSessionCashService $sessionCash,
        private readonly InventoryControlEngine $inventoryControl,
        private readonly OrderLifecycleEngine $lifecycle,
    ) {}

    /**
     * @param  list<array{product_id: string, product_variant_id?: string|null, quantity: int}>  $lines
     * @return array{
     *   lines: list<array<string, mixed>>,
     *   subtotal: string,
     *   discount_total: string,
     *   grand_total: string,
     *   currency: string,
     *   promotion: array<string, mixed>|null
     * }
     */
    public function quote(
        Admin $cashier,
        PosSession $session,
        array $lines,
        ?User $customer = null,
        ?string $promotionCode = null,
        ?string $promotionId = null,
    ): array {
        $this->assertSession($cashier, $session);
        $store = $session->store()->firstOrFail();
        $prepared = $this->prepareLines($store, $lines);
        $subtotal = $this->sumSubtotal($prepared);

        $resolution = $this->resolvePromotion(
            $prepared,
            $subtotal,
            $customer,
            $promotionCode,
            $promotionId,
        );

        $discount = $resolution->discountTotal;
        $grand = bcsub($subtotal, $discount, 2);
        if (bccomp($grand, '0.00', 2) < 0) {
            $grand = '0.00';
        }

        return [
            'lines' => array_map(fn (array $row) => [
                'product_id' => $row['product']->id,
                'product_name' => $row['product']->name,
                'product_variant_id' => $row['variant']->id,
                'variant_name' => $row['variant']->name,
                'variant_sku' => $row['variant']->sku,
                'quantity' => $row['quantity'],
                'unit_price' => $row['unit_price'],
                'line_total' => $row['line_total'],
                'available_stock' => $row['available_stock'],
            ], $prepared),
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'grand_total' => $grand,
            'currency' => 'TZS',
            'promotion' => $resolution->applications[0] ?? null,
        ];
    }

    /**
     * @param  list<array{product_id: string, product_variant_id?: string|null, quantity: int}>  $lines
     * @return array{order: Order, receipt: PosReceipt, payment: Payment, change: string|null, quote: array<string, mixed>, idempotent_replay?: bool}
     */
    public function complete(
        Admin $cashier,
        PosSession $session,
        array $lines,
        string $paymentMethodCode,
        ?float $amountReceived = null,
        bool $manualConfirmed = false,
        ?User $customer = null,
        ?string $promotionCode = null,
        ?string $promotionId = null,
        ?string $idempotencyKey = null,
    ): array {
        $this->assertSession($cashier, $session);
        $store = $session->store()->firstOrFail();
        try {
            $this->storeContext->assertCanAccess($cashier, $store);
        } catch (ValidationException) {
            PosErrors::storeAccessDenied();
        }

        $idempotencyKey = filled($idempotencyKey) ? trim((string) $idempotencyKey) : null;
        if ($idempotencyKey !== null) {
            if (strlen($idempotencyKey) > 128) {
                PosErrors::fail('idempotency_key', 'Idempotency key must be at most 128 characters.');
            }

            $existing = PosSaleIdempotencyKey::query()
                ->where('admin_id', $cashier->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing !== null) {
                Log::info('pos.sale_idempotent_replay', [
                    'admin_id' => $cashier->id,
                    'order_id' => $existing->order_id,
                    'idempotency_key' => $idempotencyKey,
                ]);

                return $this->replayCompletedSale($existing->order_id);
            }
        }

        $method = $this->resolvePaymentMethod($paymentMethodCode);
        $handler = PosPaymentHandler::tryFrom((string) (($method->config ?? [])['handler'] ?? ''))
            ?? PosPaymentHandler::ManualConfirm;

        $tzChannel = $this->channels->channelByCode(CommerceChannelCode::TzLocal);
        $location = $this->stores->defaultLocation($store);

        try {
            return DB::transaction(function () use (
            $cashier,
            $session,
            $store,
            $lines,
            $method,
            $handler,
            $amountReceived,
            $idempotencyKey,
            $manualConfirmed,
            $customer,
            $promotionCode,
            $promotionId,
            $tzChannel,
            $location,
        ) {
            $prepared = $this->prepareLines($store, $lines, lockStock: true);
            $subtotal = $this->sumSubtotal($prepared);
            $resolution = $this->resolvePromotion(
                $prepared,
                $subtotal,
                $customer,
                $promotionCode,
                $promotionId,
            );
            $discount = $resolution->discountTotal;
            $grand = bcsub($subtotal, $discount, 2);
            if (bccomp($grand, '0.00', 2) < 0) {
                $grand = '0.00';
            }

            $change = $this->assertPayment($handler, $grand, $amountReceived, $manualConfirmed);

            $order = Order::query()->create([
                'user_id' => $customer?->id,
                'store_id' => $store->id,
                'sales_origin' => SalesOrigin::Pos,
                'commerce_channel_id' => $tzChannel->id,
                'commerce_channel_snapshot' => $this->channels->snapshot($tzChannel),
                'pos_session_id' => $session->id,
                'order_number' => $this->orderNumbers->generate(),
                'status' => OrderStatus::Paid,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'tax_amount' => '0.00',
                'shipping_amount' => '0.00',
                'total' => $grand,
                'currency' => 'TZS',
                'is_demo' => false,
                'placed_at' => now(),
                'paid_at' => now(),
            ]);

            foreach ($prepared as $row) {
                $payload = $this->snapshots->snapshotFromCatalog(
                    $row['product'],
                    $row['variant'],
                    $row['quantity'],
                    $row['unit_price'],
                    'TZS',
                );
                $order->items()->create($payload);

                $row['inventory']->forceFill([
                    'inventory_location_id' => $row['inventory']->inventory_location_id ?? $location->id,
                    'warehouse_code' => $location->code,
                ])->save();

                $this->inventoryControl->recordSale(
                    $row['inventory']->fresh() ?? $row['inventory'],
                    $row['quantity'],
                    $cashier,
                    Order::class,
                    $order->id,
                );
            }

            $this->promotionUsages->recordForOrder($order, $resolution, $customer);

            $paymentEnum = match (strtoupper($method->code)) {
                'CASH' => PaymentMethod::Cash,
                'MPESA_LIPA', 'MPESA' => PaymentMethod::Mpesa,
                'NMB_BANK', 'NMB' => PaymentMethod::Nmb,
                default => PaymentMethod::BankTransfer,
            };

            $promoMeta = $resolution->applications[0] ?? null;

            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'user_id' => $customer?->id,
                'method' => $paymentEnum,
                'status' => PaymentStatus::Paid,
                'amount' => $grand,
                'currency' => 'TZS',
                'reference' => $method->code,
                'initiated_at' => now(),
                'paid_at' => now(),
                'metadata' => [
                    'pos' => true,
                    'payment_method_code' => $method->code,
                    'handler' => $handler->value,
                    'amount_received' => $amountReceived,
                    'change' => $change,
                    'cashier_id' => $cashier->id,
                    'promotion' => $promoMeta,
                ],
            ]);

            // Explicit POS create-as-paid — audited via lifecycle history (source=pos).
            // In-store takeaway: fulfillment/warehouse not applicable for counter sales.
            $this->lifecycle->recordCreated(
                $order,
                new OrderLifecycleContext(
                    source: 'pos',
                    reason: 'POS sale created as paid (verified counter payment)',
                    admin: $cashier,
                    idempotencyKey: $idempotencyKey !== null
                        ? 'pos-created:'.$idempotencyKey
                        : 'pos-created:'.$order->id,
                    metadata: [
                        'immediate_paid' => true,
                        'sales_origin' => SalesOrigin::Pos->value,
                        'pos_session_id' => $session->id,
                        'store_id' => $store->id,
                        'payment_id' => $payment->id,
                        'payment_method' => $method->code,
                        'payment_reference' => $method->code,
                        'amount' => $grand,
                        'currency' => 'TZS',
                    ],
                ),
            );

            $receipt = $this->receipts->issueForSale(
                $order->fresh(),
                $store,
                $session->load('terminal'),
                $cashier,
                $payment,
                $customer,
                $change,
                $amountReceived,
                array_map(fn (array $row) => [
                    'name' => $row['product']->name,
                    'variant' => $row['variant']->name,
                    'sku' => $row['variant']->sku,
                    'qty' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'discount' => '0.00',
                    'line_total' => $row['line_total'],
                ], $prepared),
                $promoMeta,
            );

            $freshOrder = $order->fresh(['items', 'store', 'user', 'payments']) ?? $order;

            // Reuse commerce + CRM lifecycle (same events as online checkout).
            event(new CommerceOrderCreated($freshOrder, $tzChannel, $this->channels->snapshot($tzChannel)));
            event(PaymentConfirmed::fromOrder($freshOrder));
            event(StorePlatformAudit::saleCompleted($freshOrder, $cashier));

            // Reuse Profit Engine — cost snapshots + margin after paid POS sale.
            try {
                $this->profits->calculateForOrder($freshOrder, $cashier);
            } catch (\Throwable $e) {
                Log::warning('pos.profit_calculation_failed', [
                    'order_id' => $freshOrder->id,
                    'message' => $e->getMessage(),
                ]);
            }

            if ($idempotencyKey !== null) {
                PosSaleIdempotencyKey::query()->create([
                    'idempotency_key' => $idempotencyKey,
                    'admin_id' => $cashier->id,
                    'pos_session_id' => $session->id,
                    'order_id' => $freshOrder->id,
                ]);
            }

            $this->sessionCash->persistRunningTotals($session->fresh() ?? $session);

            $quote = [
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'grand_total' => $grand,
                'currency' => 'TZS',
                'promotion' => $promoMeta,
            ];

            Log::info('pos.sale_complete', [
                'order_id' => $freshOrder->id,
                'store_id' => $store->id,
                'session_id' => $session->id,
                'admin_id' => $cashier->id,
                'total' => $grand,
                'payment_method' => $method->code,
            ]);

            return [
                'order' => $freshOrder->fresh(['items', 'store', 'user']) ?? $freshOrder,
                'receipt' => $receipt,
                'payment' => $payment,
                'change' => $change,
                'quote' => $quote,
            ];
        });
        } catch (UniqueConstraintViolationException $e) {
            if ($idempotencyKey === null) {
                throw $e;
            }

            $existing = PosSaleIdempotencyKey::query()
                ->where('admin_id', $cashier->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing === null) {
                throw $e;
            }

            Log::info('pos.sale_idempotent_replay_race', [
                'admin_id' => $cashier->id,
                'order_id' => $existing->order_id,
                'idempotency_key' => $idempotencyKey,
            ]);

            return $this->replayCompletedSale($existing->order_id);
        }
    }

    /**
     * @return array{order: Order, receipt: PosReceipt, payment: Payment|null, change: string|null, quote: array<string, mixed>, idempotent_replay: bool}
     */
    private function replayCompletedSale(string $orderId): array
    {
        $order = Order::query()->with(['items', 'store', 'user', 'payments'])->findOrFail($orderId);
        $payment = $order->payments->first();
        $receipt = PosReceipt::query()->where('order_id', $order->id)->first();
        if ($receipt === null) {
            PosErrors::receiptNotFound();
        }

        $meta = is_array($payment?->metadata) ? $payment->metadata : [];

        return [
            'order' => $order,
            'receipt' => $receipt,
            'payment' => $payment,
            'change' => isset($meta['change']) ? (string) $meta['change'] : null,
            'quote' => [
                'subtotal' => number_format((float) $order->subtotal, 2, '.', ''),
                'discount_total' => number_format((float) $order->discount_amount, 2, '.', ''),
                'grand_total' => number_format((float) $order->total, 2, '.', ''),
                'currency' => (string) ($order->currency ?: 'TZS'),
                'promotion' => $meta['promotion'] ?? null,
            ],
            'idempotent_replay' => true,
        ];
    }

    public function show(Admin $cashier, Order $order): Order
    {
        if ($order->sales_origin !== SalesOrigin::Pos) {
            throw ValidationException::withMessages([
                'order' => ['Not a POS sale.'],
            ]);
        }

        if ($order->store_id) {
            $this->storeContext->assertCanAccess($cashier, $order->store()->firstOrFail());
        }

        return $order->load(['items', 'store', 'user', 'payments']);
    }

    private function assertSession(Admin $cashier, PosSession $session): void
    {
        if (! $session->isOpen()) {
            PosErrors::sessionClosed();
        }

        if ($session->admin_id !== $cashier->id && ! $cashier->is_super_admin) {
            PosErrors::fail('session', 'Session does not belong to this cashier.');
        }
    }

    private function resolvePaymentMethod(string $code): PaymentMethodDefinition
    {
        $method = PaymentMethodDefinition::query()
            ->where('code', strtoupper($code))
            ->where('is_active', true)
            ->first();

        if ($method === null || (($method->config['pos_enabled'] ?? true) === false)) {
            throw ValidationException::withMessages([
                'payment_method' => ['Payment method is not available.'],
            ]);
        }

        return $method;
    }

    /**
     * @param  list<array{product_id: string, product_variant_id?: string|null, quantity: int}>  $lines
     * @return list<array<string, mixed>>
     */
    private function prepareLines(\App\Models\Store $store, array $lines, bool $lockStock = false): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages([
                'items' => ['At least one line item is required.'],
            ]);
        }

        $tzChannel = $this->channels->channelByCode(CommerceChannelCode::TzLocal);
        $location = $this->stores->defaultLocation($store);
        $prepared = [];

        foreach ($lines as $index => $line) {
            $product = Product::query()->with(['brand', 'images'])->find($line['product_id'] ?? null);
            if ($product === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => ['Product not found.'],
                ]);
            }

            if ($product->store_id !== $store->id) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => ['Product does not belong to this store.'],
                ]);
            }

            if ($product->commerce_channel_id !== $tzChannel->id) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => ['Only TZ_LOCAL products can be sold on POS.'],
                ]);
            }

            $variantId = $line['product_variant_id'] ?? null;
            $variant = $variantId
                ? ProductVariant::query()->with('prices')->whereKey($variantId)->where('product_id', $product->id)->first()
                : $product->variants()->with('prices')->where('is_active', true)->orderBy('sort_order')->first();

            if ($variant === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_variant_id" => ['Product variant is required.'],
                ]);
            }

            $qty = max(1, (int) ($line['quantity'] ?? 1));
            $inventoryQuery = VariantInventory::query()
                ->where('product_variant_id', $variant->id)
                ->where(function ($q) use ($location) {
                    $q->where('inventory_location_id', $location->id)
                        ->orWhere('warehouse_code', $location->code);
                });

            if ($lockStock) {
                $inventoryQuery->lockForUpdate();
            }

            $inventory = $inventoryQuery->first();
            $available = $inventory?->available() ?? 0;

            if ($inventory === null || $available < $qty) {
                PosErrors::insufficientInventory("items.{$index}.quantity");
            }

            $unit = $this->catalog->resolveRetailAmount($variant);
            if ($unit === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_variant_id" => ['No active retail price found for this variant.'],
                ]);
            }

            $lineTotal = bcmul($unit, (string) $qty, 2);

            $prepared[] = [
                'product' => $product,
                'variant' => $variant,
                'inventory' => $inventory,
                'quantity' => $qty,
                'unit_price' => $unit,
                'line_total' => $lineTotal,
                'available_stock' => $available,
            ];
        }

        return $prepared;
    }

    /**
     * @param  list<array<string, mixed>>  $prepared
     */
    private function sumSubtotal(array $prepared): string
    {
        $subtotal = '0.00';
        foreach ($prepared as $row) {
            $subtotal = bcadd($subtotal, $row['line_total'], 2);
        }

        return $subtotal;
    }

    /**
     * @param  list<array<string, mixed>>  $prepared
     */
    private function resolvePromotion(
        array $prepared,
        string $subtotal,
        ?User $customer,
        ?string $promotionCode,
        ?string $promotionId,
    ): \App\Services\Promotions\DTOs\DiscountResolution {
        $empty = new \App\Services\Promotions\DTOs\DiscountResolution(
            subtotal: $subtotal,
            discountTotal: '0.00',
            shippingTotal: '0.00',
            currency: 'TZS',
            applications: [],
            lineAllocations: [],
            freeShipping: false,
            primaryPromotion: null,
            estimatedMarginPercentage: null,
            marginRejected: false,
            marginMessage: null,
        );

        $user = $customer ?? User::make([
            'id' => (string) Str::uuid(),
            'name' => 'Walk-in Customer',
            'email' => 'walkin-pos@local.test',
        ]);

        $cart = Cart::query()->create([
            'user_id' => $customer?->id,
            'status' => CartStatus::Active,
        ]);

        try {
            foreach ($prepared as $row) {
                CartItem::query()->create([
                    'cart_id' => $cart->id,
                    'product_id' => $row['product']->id,
                    'product_variant_id' => $row['variant']->id,
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'price_snapshot' => $row['unit_price'],
                    'currency' => 'TZS',
                ]);
            }

            $cart->load(['items.product', 'items.variant']);

            return $this->discounts->resolve(
                $user,
                $cart,
                $subtotal,
                'TZS',
                $promotionCode,
                $promotionId,
                ['allow_low_margin' => true],
            );
        } catch (ValidationException $e) {
            if (($promotionCode === null || trim((string) $promotionCode) === '') && $promotionId === null) {
                return $empty;
            }
            throw $e;
        } finally {
            CartItem::query()->where('cart_id', $cart->id)->forceDelete();
            $cart->forceDelete();
        }
    }

    private function assertPayment(
        PosPaymentHandler $handler,
        string $grand,
        ?float $amountReceived,
        bool $manualConfirmed,
    ): ?string {
        if ($handler === PosPaymentHandler::CashWithChange) {
            if ($amountReceived === null) {
                throw ValidationException::withMessages([
                    'amount_received' => ['Amount received is required for cash payments.'],
                ]);
            }
            $received = number_format($amountReceived, 2, '.', '');
            if (bccomp($received, $grand, 2) < 0) {
                throw ValidationException::withMessages([
                    'amount_received' => ['Amount received is less than the sale total.'],
                ]);
            }

            return bcsub($received, $grand, 2);
        }

        if ($handler === PosPaymentHandler::ManualConfirm && ! $manualConfirmed) {
            throw ValidationException::withMessages([
                'manual_confirmed' => ['Cashier must confirm that payment was received.'],
            ]);
        }

        return null;
    }
}

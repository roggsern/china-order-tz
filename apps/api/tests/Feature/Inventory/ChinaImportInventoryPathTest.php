<?php

namespace Tests\Feature\Inventory;

use App\Enums\CommerceChannelCode;
use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\ProductLifecycleStatus;
use App\Enums\VariantPriceType;
use App\Events\Audit\PaymentConfirmed;
use App\Models\ChinaCommercialStock;
use App\Models\ChinaProcurementRequirement;
use App\Models\CommerceChannel;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use App\Models\PaymentTransaction;
use App\Services\Inventory\DTOs\InventoryCommitmentContext;
use App\Services\Inventory\DTOs\ReservationContext;
use App\Services\Inventory\InventoryCommitmentService;
use App\Services\Inventory\ReservationService;
use App\Services\Inventory\StockResolver;
use App\Services\Payments\Orchestration\DTOs\PaymentProviderResult;
use App\Services\Payments\Orchestration\PaymentTransactionCompletionService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChinaImportInventoryPathTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_stock_resolver_uses_commercial_stock_for_china_import_variant(): void
    {
        ['product' => $product, 'variant' => $variant] = $this->chinaVariantFixtures(
            commercialAvailable: 15,
            mainOnHand: 50,
        );

        $result = app(StockResolver::class)->resolveVariantProduct($variant, null, $product);

        $this->assertTrue($result->resolved);
        $this->assertSame('china_commercial_stocks', $result->source);
        $this->assertSame('commercial', $result->inventoryType);
        $this->assertSame(15, $result->quantityAvailable);
        $this->assertNull($result->inventory);
        $this->assertTrue($result->hasInventoryPolicy());
        $this->assertSame('commercial', $result->meta['inventory_source']);
    }

    public function test_stock_resolver_uses_main_inventory_for_tz_local_variant(): void
    {
        ['product' => $product, 'variant' => $variant, 'mainInventory' => $main] = $this->tzLocalVariantFixtures(
            mainOnHand: 20,
        );

        $result = app(StockResolver::class)->resolveVariantProduct($variant, null, $product);

        $this->assertTrue($result->resolved);
        $this->assertSame('variant_inventories', $result->source);
        $this->assertSame(20, $result->quantityAvailable);
        $this->assertSame($main->id, $result->inventory?->id);
    }

    public function test_china_import_payment_skips_main_inventory_commit(): void
    {
        ['order' => $order, 'variant' => $variant, 'mainInventory' => $main] = $this->chinaPaidOrderFixtures(
            commercialAvailable: 10,
            mainOnHand: 30,
            qty: 2,
        );

        $result = app(InventoryCommitmentService::class)->commitForOrder(new InventoryCommitmentContext(
            order: $order,
            source: 'test',
        ));

        $this->assertFalse($result->committed);
        $this->assertSame('china_import_commercial_stock', $result->meta['skip_reason']);
        $this->assertSame(30, (int) $main->fresh()->on_hand);
        $this->assertSame(0, (int) $main->fresh()->reserved);
        $this->assertDatabaseMissing('inventory_stock_movements', [
            'variant_inventory_id' => $variant->id,
        ]);
    }

    public function test_china_import_payment_decreases_commercial_stock_and_creates_procurement(): void
    {
        ['order' => $order, 'variant' => $variant, 'mainInventory' => $main] = $this->chinaPaidOrderFixtures(
            commercialAvailable: 10,
            mainOnHand: 30,
            qty: 2,
        );

        event(PaymentConfirmed::fromOrder($order));

        $stock = ChinaCommercialStock::query()
            ->where('product_variant_id', $variant->id)
            ->first();

        $this->assertNotNull($stock);
        $this->assertSame(8, (int) $stock->available_quantity);
        $this->assertSame(2, (int) $stock->reserved_quantity);
        $this->assertSame(2, (int) $stock->ordered_quantity);
        $this->assertSame(30, (int) $main->fresh()->on_hand);

        $this->assertDatabaseHas('china_procurement_requirements', [
            'product_variant_id' => $variant->id,
            'quantity_required' => 2,
        ]);
    }

    public function test_payment_completion_china_import_leaves_main_inventory_unchanged(): void
    {
        ['order' => $order, 'mainInventory' => $main] = $this->chinaPaidOrderFixtures(
            commercialAvailable: 8,
            mainOnHand: 25,
            qty: 3,
            pendingPayment: true,
        );

        $transaction = PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'status' => PaymentTransactionStatus::Pending,
            'amount' => $order->total,
            'currency' => 'TZS',
        ]);

        app(PaymentTransactionCompletionService::class)->applyResult(
            $transaction,
            new PaymentProviderResult(
                ok: true,
                status: PaymentTransactionStatus::Successful,
                providerReference: 'china-ref',
                externalTransactionId: 'china-ext',
            ),
        );

        $this->assertSame(25, (int) $main->fresh()->on_hand);
        $this->assertSame(0, (int) $main->fresh()->reserved);
        $this->assertDatabaseHas('china_procurement_requirements', [
            'product_id' => $order->items->first()->product_id,
            'quantity_required' => 3,
        ]);
    }

    public function test_tz_local_payment_still_commits_main_inventory(): void
    {
        ['order' => $order, 'mainInventory' => $main] = $this->tzLocalPaidOrderFixtures(
            mainOnHand: 12,
            qty: 4,
            pendingPayment: true,
        );

        $transaction = PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'status' => PaymentTransactionStatus::Pending,
            'amount' => $order->total,
            'currency' => 'TZS',
        ]);

        app(PaymentTransactionCompletionService::class)->applyResult(
            $transaction,
            new PaymentProviderResult(
                ok: true,
                status: PaymentTransactionStatus::Successful,
                providerReference: 'tz-ref',
                externalTransactionId: 'tz-ext',
            ),
        );

        $this->assertSame(8, (int) $main->fresh()->on_hand);
    }

    public function test_china_import_checkout_reservation_skips_main_inventory(): void
    {
        ['session' => $session, 'mainInventory' => $main] = $this->chinaCheckoutFixtures(
            commercialAvailable: 6,
            mainOnHand: 40,
            qty: 2,
        );

        $result = app(ReservationService::class)->reserve(new ReservationContext(
            checkoutSession: $session,
            cart: $session->cart,
            source: 'test',
        ));

        $this->assertTrue($result->ok);
        $this->assertSame(0, $result->linesAffected);
        $this->assertSame(1, $result->linesIdempotent);
        $this->assertSame(40, (int) $main->fresh()->on_hand);
        $this->assertSame(0, (int) $main->fresh()->reserved);
    }

    /**
     * @return array{product: Product, variant: ProductVariant, mainInventory: VariantInventory}
     */
    private function chinaVariantFixtures(int $commercialAvailable, int $mainOnHand): array
    {
        $channel = CommerceChannel::query()->where('code', CommerceChannelCode::ChinaImport->value)->firstOrFail();
        $supplier = Supplier::factory()->create(['is_active' => true]);
        $product = Product::factory()->fromChina()->create([
            'commerce_channel_id' => $channel->id,
            'supplier_id' => $supplier->id,
            'price' => 0,
            'lifecycle_status' => ProductLifecycleStatus::Active,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);
        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 25000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        $main = VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => $mainOnHand,
            'reserved' => 0,
            'is_active' => true,
        ]);
        ChinaCommercialStock::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'available_quantity' => $commercialAvailable,
            'reserved_quantity' => 0,
            'ordered_quantity' => 0,
        ]);

        return [
            'product' => $product->fresh(['commerceChannel']),
            'variant' => $variant,
            'mainInventory' => $main,
        ];
    }

    /**
     * @return array{product: Product, variant: ProductVariant, mainInventory: VariantInventory}
     */
    private function tzLocalVariantFixtures(int $mainOnHand): array
    {
        $channel = CommerceChannel::query()->where('code', CommerceChannelCode::TzLocal->value)->firstOrFail();
        $product = Product::factory()->fromDar()->create([
            'commerce_channel_id' => $channel->id,
            'price' => 0,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);
        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 18000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        $main = VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => $mainOnHand,
            'reserved' => 0,
            'is_active' => true,
        ]);

        return [
            'product' => $product->fresh(['commerceChannel']),
            'variant' => $variant,
            'mainInventory' => $main,
        ];
    }

    /**
     * @return array{order: Order, variant: ProductVariant, mainInventory: VariantInventory}
     */
    private function chinaPaidOrderFixtures(
        int $commercialAvailable,
        int $mainOnHand,
        int $qty,
        bool $pendingPayment = false,
    ): array {
        ['product' => $product, 'variant' => $variant, 'mainInventory' => $main] = $this->chinaVariantFixtures(
            $commercialAvailable,
            $mainOnHand,
        );
        $channel = CommerceChannel::query()->where('code', CommerceChannelCode::ChinaImport->value)->firstOrFail();
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => $pendingPayment ? OrderStatus::PendingPayment : OrderStatus::Paid,
            'paid_at' => $pendingPayment ? null : now(),
            'commerce_channel_id' => $channel->id,
            'commerce_channel_snapshot' => [
                'id' => $channel->id,
                'code' => CommerceChannelCode::ChinaImport->value,
                'name' => $channel->name,
            ],
            'subtotal' => 25000 * $qty,
            'total' => 25000 * $qty,
            'currency' => 'TZS',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => $variant->name,
            'sku' => $variant->sku,
            'quantity' => $qty,
            'unit_price' => 25000,
            'line_total' => 25000 * $qty,
            'currency' => 'TZS',
        ]);

        return [
            'order' => $order->fresh(['items.product', 'items.variant']),
            'variant' => $variant,
            'mainInventory' => $main,
        ];
    }

    /**
     * @return array{order: Order, mainInventory: VariantInventory}
     */
    private function tzLocalPaidOrderFixtures(
        int $mainOnHand,
        int $qty,
        bool $pendingPayment = false,
    ): array {
        ['product' => $product, 'variant' => $variant, 'mainInventory' => $main] = $this->tzLocalVariantFixtures($mainOnHand);
        $channel = CommerceChannel::query()->where('code', CommerceChannelCode::TzLocal->value)->firstOrFail();
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => $pendingPayment ? OrderStatus::PendingPayment : OrderStatus::Paid,
            'paid_at' => $pendingPayment ? null : now(),
            'commerce_channel_id' => $channel->id,
            'commerce_channel_snapshot' => [
                'id' => $channel->id,
                'code' => CommerceChannelCode::TzLocal->value,
                'name' => $channel->name,
            ],
            'subtotal' => 18000 * $qty,
            'total' => 18000 * $qty,
            'currency' => 'TZS',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => $variant->name,
            'sku' => $variant->sku,
            'quantity' => $qty,
            'unit_price' => 18000,
            'line_total' => 18000 * $qty,
            'currency' => 'TZS',
        ]);

        return [
            'order' => $order->fresh(['items.product', 'items.variant']),
            'mainInventory' => $main,
        ];
    }

    /**
     * @return array{session: \App\Models\CheckoutSession, mainInventory: VariantInventory}
     */
    private function chinaCheckoutFixtures(int $commercialAvailable, int $mainOnHand, int $qty): array
    {
        ['product' => $product, 'variant' => $variant, 'mainInventory' => $main] = $this->chinaVariantFixtures(
            $commercialAvailable,
            $mainOnHand,
        );
        $user = User::factory()->create();
        $cart = \App\Models\Cart::factory()->create(['user_id' => $user->id]);
        \App\Models\CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => $qty,
            'unit_price' => 25000,
            'price_snapshot' => 25000,
        ]);
        $session = \App\Models\CheckoutSession::factory()->create([
            'user_id' => $user->id,
            'cart_id' => $cart->id,
        ]);

        return [
            'session' => $session->fresh(['cart.items.product', 'cart.items.variant']),
            'mainInventory' => $main,
        ];
    }
}

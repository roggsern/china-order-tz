<?php

namespace Tests\Unit\Services\Inventory;

use App\Enums\CheckoutSessionStatus;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CheckoutSession;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantInventory;
use App\Services\Inventory\DTOs\ReservationContext;
use App\Services\Inventory\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2A-3B-4 — ReservationService (ADR 055).
 */
class ReservationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReservationService $reservations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reservations = app(ReservationService::class);
    }

    public function test_reserves_simple_stock(): void
    {
        ['session' => $session, 'inventory' => $inventory] = $this->makeSimpleCheckout(onHand: 10, qty: 3);

        $result = $this->reservations->reserve(new ReservationContext(
            checkoutSession: $session,
            cart: $session->cart,
            source: 'test',
        ));

        $this->assertTrue($result->ok);
        $this->assertSame('reserve', $result->operation);
        $this->assertSame(1, $result->linesAffected);
        $this->assertSame(3, (int) $inventory->fresh()->reserved_quantity);
        $this->assertSame(10, (int) $inventory->fresh()->quantity);
    }

    public function test_reserves_variant_stock(): void
    {
        ['session' => $session, 'inventory' => $inventory] = $this->makeVariantCheckout(onHand: 12, qty: 4);

        $this->reservations->reserve(new ReservationContext(
            checkoutSession: $session,
            cart: $session->cart,
            source: 'test',
        ));

        $this->assertSame(4, (int) $inventory->fresh()->reserved);
        $this->assertSame(12, (int) $inventory->fresh()->on_hand);
    }

    public function test_releases_stock(): void
    {
        ['session' => $session, 'inventory' => $inventory] = $this->makeVariantCheckout(onHand: 8, qty: 2);

        $this->reservations->reserve(new ReservationContext(
            checkoutSession: $session,
            cart: $session->cart,
            source: 'test',
        ));
        $this->assertSame(2, (int) $inventory->fresh()->reserved);

        $released = $this->reservations->release(new ReservationContext(
            checkoutSession: $session,
            cart: $session->cart,
            source: 'test',
        ));

        $this->assertTrue($released->ok);
        $this->assertSame('release', $released->operation);
        $this->assertSame(0, (int) $inventory->fresh()->reserved);
    }

    public function test_expires_reservation(): void
    {
        ['session' => $session, 'inventory' => $inventory] = $this->makeSimpleCheckout(onHand: 5, qty: 2);

        $this->reservations->reserve(new ReservationContext(
            checkoutSession: $session,
            cart: $session->cart,
            source: 'test',
        ));

        $expired = $this->reservations->expire(new ReservationContext(
            checkoutSession: $session,
            cart: $session->cart,
            source: 'test',
        ));

        $this->assertSame('expire', $expired->operation);
        $this->assertSame(0, (int) $inventory->fresh()->reserved_quantity);
    }

    public function test_duplicate_reserve_is_idempotent(): void
    {
        ['session' => $session, 'inventory' => $inventory] = $this->makeVariantCheckout(onHand: 6, qty: 2);

        $this->reservations->reserve(new ReservationContext(
            checkoutSession: $session,
            cart: $session->cart,
            source: 'test',
        ));
        $second = $this->reservations->reserve(new ReservationContext(
            checkoutSession: $session,
            cart: $session->cart,
            source: 'test',
        ));

        $this->assertSame(1, $second->linesIdempotent);
        $this->assertSame(2, (int) $inventory->fresh()->reserved);
    }

    public function test_duplicate_release_is_idempotent(): void
    {
        ['session' => $session, 'inventory' => $inventory] = $this->makeSimpleCheckout(onHand: 6, qty: 2);

        $this->reservations->reserve(new ReservationContext(
            checkoutSession: $session,
            cart: $session->cart,
            source: 'test',
        ));
        $this->reservations->release(new ReservationContext(
            checkoutSession: $session,
            cart: $session->cart,
            source: 'test',
        ));
        $second = $this->reservations->release(new ReservationContext(
            checkoutSession: $session,
            cart: $session->cart,
            source: 'test',
        ));

        $this->assertGreaterThanOrEqual(1, $second->linesIdempotent);
        $this->assertSame(0, (int) $inventory->fresh()->reserved_quantity);
    }

    public function test_convert_to_commit_requires_order(): void
    {
        ['session' => $session] = $this->makeSimpleCheckout(onHand: 4, qty: 1);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->reservations->convertToCommit(new ReservationContext(
            checkoutSession: $session,
            cart: $session->cart,
            source: 'test',
        ));
    }

    public function test_sync_re_reserves_after_release_cycle(): void
    {
        ['session' => $session, 'inventory' => $inventory, 'cart' => $cart] = $this->makeVariantCheckout(onHand: 10, qty: 3);

        $this->reservations->reserve(new ReservationContext(
            checkoutSession: $session,
            cart: $cart,
            source: 'test',
        ));
        $this->assertSame(3, (int) $inventory->fresh()->reserved);

        $synced = $this->reservations->syncForCheckout($session, $cart);

        $this->assertTrue($synced->ok);
        $this->assertSame(3, (int) $inventory->fresh()->reserved);
    }

    /**
     * @return array{session: CheckoutSession, inventory: Inventory, cart: Cart}
     */
    private function makeSimpleCheckout(int $onHand, int $qty): array
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Simple Reserve Product',
            'price' => 10000,
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'visibility' => ProductVisibility::Public,
            'is_demo' => false,
        ]);
        $inventory = Inventory::query()->updateOrCreate(
            ['product_id' => $product->id, 'product_variant_id' => null],
            ['quantity' => $onHand, 'reserved_quantity' => 0, 'low_stock_threshold' => 1],
        );
        $cart = Cart::factory()->create(['user_id' => $user->id, 'currency' => 'TZS']);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => $qty,
            'unit_price' => 10000,
            'currency' => 'TZS',
        ]);
        $session = CheckoutSession::factory()->validated()->create([
            'user_id' => $user->id,
            'cart_id' => $cart->id,
            'status' => CheckoutSessionStatus::Validated,
            'expires_at' => now()->addMinutes(30),
        ]);

        return [
            'session' => $session->fresh(['cart.items']) ?? $session,
            'inventory' => $inventory,
            'cart' => $cart->fresh('items') ?? $cart,
        ];
    }

    /**
     * @return array{session: CheckoutSession, inventory: VariantInventory, cart: Cart}
     */
    private function makeVariantCheckout(int $onHand, int $qty): array
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Variant Reserve Product',
            'price' => 0,
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_demo' => false,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'name' => 'Size M',
            'sku' => 'VAR-RESERVE',
        ]);
        $inventory = VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => $onHand,
            'reserved' => 0,
            'is_active' => true,
        ]);
        $cart = Cart::factory()->create(['user_id' => $user->id, 'currency' => 'TZS']);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => $qty,
            'unit_price' => 20000,
            'currency' => 'TZS',
        ]);
        $session = CheckoutSession::factory()->validated()->create([
            'user_id' => $user->id,
            'cart_id' => $cart->id,
            'status' => CheckoutSessionStatus::Validated,
            'expires_at' => now()->addMinutes(30),
        ]);

        return [
            'session' => $session->fresh(['cart.items']) ?? $session,
            'inventory' => $inventory,
            'cart' => $cart->fresh('items') ?? $cart,
        ];
    }
}

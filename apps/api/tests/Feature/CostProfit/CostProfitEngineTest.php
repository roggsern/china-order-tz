<?php

namespace Tests\Feature\CostProfit;

use App\Enums\ActivityEventType;
use App\Enums\CartStatus;
use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderCostSnapshot;
use App\Models\ProductShippingOption;
use App\Models\ProfitRecord;
use App\Models\Supplier;
use App\Models\SupplierCostHistory;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\CostProfit\CostEngine;
use App\Services\CostProfit\ProfitEngine;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CostProfitEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_creation_captures_immutable_cost_snapshot(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(25000);

        $supplier = Supplier::factory()->create(['is_active' => true]);
        $product->update(['supplier_id' => $supplier->id, 'cost_price' => 5000]);

        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_variant_id' => $variant->id,
            'supplier_sku' => 'COST-SKU-1',
            'purchase_cost' => 8000,
            'currency' => 'TZS',
            'is_active' => true,
        ]);

        SupplierCostHistory::query()->create([
            'supplier_id' => $supplier->id,
            'product_variant_id' => $variant->id,
            'purchase_cost' => 9000,
            'currency' => 'TZS',
            'recorded_at' => now(),
        ]);

        ProductShippingOption::query()->where('product_id', $product->id)->forceDelete();
        ProductShippingOption::factory()->air(2500)->create(['product_id' => $product->id]);

        $this->seedCart($user, $product->id, $variant->id, 2, 25000);

        Sanctum::actingAs($user);
        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');
        $this->applyCheckoutShippingChoice($sessionId);
        $orderId = $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")
            ->assertCreated()
            ->json('data.id');

        $order = Order::query()->with('items')->findOrFail($orderId);
        $item = $order->items->first();
        $this->assertNotNull($item);

        $snapshot = OrderCostSnapshot::query()->where('order_item_id', $item->id)->first();
        $this->assertNotNull($snapshot);
        $this->assertSame('18000.00', (string) $snapshot->supplier_cost); // 9000 * 2
        $this->assertSame('TZS', $snapshot->currency);
        $this->assertSame('1.00000000', (string) $snapshot->exchange_rate);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::OrderCostCaptured->value,
            'subject_id' => $orderId,
        ]);
    }

    public function test_supplier_and_shipping_price_changes_do_not_rewrite_snapshots(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(20000);

        $supplier = Supplier::factory()->create(['is_active' => true]);
        $product->update(['supplier_id' => $supplier->id]);

        SupplierCostHistory::query()->create([
            'supplier_id' => $supplier->id,
            'product_variant_id' => $variant->id,
            'purchase_cost' => 7000,
            'currency' => 'TZS',
            'recorded_at' => now()->subDay(),
        ]);

        ProductShippingOption::query()->where('product_id', $product->id)->forceDelete();
        $shipping = ProductShippingOption::factory()->air(1500)->create(['product_id' => $product->id]);

        $this->seedCart($user, $product->id, $variant->id, 1, 20000);
        Sanctum::actingAs($user);
        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');
        $this->applyCheckoutShippingChoice($sessionId);
        $orderId = $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")
            ->assertCreated()
            ->json('data.id');

        $itemId = Order::query()->with('items')->findOrFail($orderId)->items->first()->id;
        $before = OrderCostSnapshot::query()->where('order_item_id', $itemId)->firstOrFail();
        $capturedSupplier = (string) $before->supplier_cost;
        $capturedShipping = (string) $before->shipping_cost;
        $capturedRate = (string) $before->exchange_rate;

        SupplierCostHistory::query()->create([
            'supplier_id' => $supplier->id,
            'product_variant_id' => $variant->id,
            'purchase_cost' => 12000,
            'currency' => 'TZS',
            'recorded_at' => now(),
        ]);
        $shipping->update(['price' => 9999]);
        config(['cost_profit.exchange_rates.TZS' => 2]);

        $after = $before->fresh();
        $this->assertSame($capturedSupplier, (string) $after->supplier_cost);
        $this->assertSame($capturedShipping, (string) $after->shipping_cost);
        $this->assertSame($capturedRate, (string) $after->exchange_rate);

        // Re-capture is idempotent and must not overwrite.
        app(CostEngine::class)->captureForOrder(Order::query()->findOrFail($orderId));
        $still = OrderCostSnapshot::query()->where('order_item_id', $itemId)->firstOrFail();
        $this->assertSame($capturedSupplier, (string) $still->supplier_cost);
        $this->assertSame($capturedShipping, (string) $still->shipping_cost);
    }

    public function test_profit_calculated_after_paid_and_dashboard_uses_records(): void
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000);

        $supplier = Supplier::factory()->create(['is_active' => true, 'code' => 'PROF_SUP_1']);
        $product->update([
            'supplier_id' => $supplier->id,
            'name' => 'Margin Phone',
        ]);

        SupplierCostHistory::query()->create([
            'supplier_id' => $supplier->id,
            'product_variant_id' => $variant->id,
            'purchase_cost' => 4000,
            'currency' => 'TZS',
            'recorded_at' => now(),
        ]);

        $this->seedCart($user, $product->id, $variant->id, 1, 10000);
        Sanctum::actingAs($user);
        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');
        $this->applyCheckoutShippingChoice($sessionId);
        $orderId = $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")
            ->assertCreated()
            ->json('data.id');

        $order = Order::query()->findOrFail($orderId);
        $order->update([
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);

        $record = app(ProfitEngine::class)->calculateForOrder($order->fresh());
        $this->assertSame('10000.00', (string) $record->revenue);
        $this->assertSame('4000.00', (string) $record->total_cost);
        $this->assertSame('6000.00', (string) $record->gross_profit);
        $this->assertEqualsWithDelta(60.0, (float) $record->margin_percentage, 0.01);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::ProfitCalculated->value,
            'subject_id' => $record->id,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/profits/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.revenue', '10000.00')
            ->assertJsonPath('data.summary.total_cost', '4000.00')
            ->assertJsonPath('data.summary.gross_profit', '6000.00')
            ->assertJsonPath('data.top_products.0.product_name', 'Margin Phone')
            ->assertJsonPath('data.suppliers.0.supplier_code', 'PROF_SUP_1');

        $this->getJson('/api/v1/admin/profits/orders')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.order_id', $orderId);

        $this->getJson('/api/v1/admin/profits/products')
            ->assertOk()
            ->assertJsonPath('data.top.0.product_id', $product->id);

        $this->getJson('/api/v1/admin/profits/suppliers')
            ->assertOk()
            ->assertJsonPath('data.0.supplier_id', $supplier->id);
    }

    public function test_profit_apis_require_admin_authorization(): void
    {
        $this->getJson('/api/v1/admin/profits/dashboard')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/profits/dashboard')->assertUnauthorized();
        $this->getJson('/api/v1/admin/profits/orders')->assertUnauthorized();
        $this->getJson('/api/v1/admin/profits/products')->assertUnauthorized();
        $this->getJson('/api/v1/admin/profits/suppliers')->assertUnauthorized();
    }

    public function test_currency_exchange_rate_is_snapshotted(): void
    {
        config(['cost_profit.exchange_rates.USD' => 2500]);

        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(100, currency: 'USD');
        $product->update(['cost_price' => 40]);

        $this->seedCart($user, $product->id, $variant->id, 1, 100, 'USD');
        Sanctum::actingAs($user);
        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');
        $this->applyCheckoutShippingChoice($sessionId);
        $orderId = $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")
            ->assertCreated()
            ->json('data.id');

        $itemId = Order::query()->with('items')->findOrFail($orderId)->items->first()->id;
        $snapshot = OrderCostSnapshot::query()->where('order_item_id', $itemId)->firstOrFail();

        $this->assertSame('USD', $snapshot->currency);
        $this->assertSame('2500.00000000', (string) $snapshot->exchange_rate);

        config(['cost_profit.exchange_rates.USD' => 9999]);
        $this->assertSame('2500.00000000', (string) $snapshot->fresh()->exchange_rate);

        // Profit record keeps its own currency; does not re-read FX config.
        $order = Order::query()->findOrFail($orderId);
        $order->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);
        $record = app(ProfitEngine::class)->calculateForOrder($order->fresh());
        $this->assertSame('USD', $record->currency);

        config(['cost_profit.exchange_rates.USD' => 1]);
        $again = app(ProfitEngine::class)->calculateForOrder($order->fresh(), force: true);
        $this->assertSame((string) $record->gross_profit, (string) $again->gross_profit);
        $this->assertSame('2500.00000000', (string) $snapshot->fresh()->exchange_rate);
        $this->assertSame(1, ProfitRecord::query()->where('order_id', $orderId)->count());
    }

    private function seedCart(
        User $user,
        string $productId,
        string $variantId,
        int $quantity,
        float $unitPrice,
        string $currency = 'TZS',
    ): Cart {
        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
            'currency' => $currency,
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'price_snapshot' => $unitPrice,
            'currency' => $currency,
        ]);

        return $cart;
    }
}

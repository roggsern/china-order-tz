<?php

namespace Tests\Feature\Privacy;

use App\Enums\CartStatus;
use App\Enums\InventoryDisposition;
use App\Enums\OrderStatus;
use App\Enums\ProductLifecycleStatus;
use App\Enums\ShipmentLifecycleStatus;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DeliveryAddress;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnItem;
use App\Models\Shipment;
use App\Models\Supplier;
use App\Models\User;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * RC1-G1: supplier / internal procurement must not leak on customer/public commerce APIs.
 */
class CustomerSerializationPrivacyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Exact forbidden keys at any JSON depth (not substrings of legitimate public fields).
     *
     * @var list<string>
     */
    private const FORBIDDEN_KEYS = [
        'supplier',
        'supplier_id',
        'suppliers',
        'contact_person',
        'payment_terms',
        'purchase_cost',
        'unit_cost',
        'cost_price',
        'landed_cost',
        'internal_margin',
        'margin_percent',
        'margin_amount',
        'procurement_notes',
        'sourcing_reference',
        'purchase_order_id',
        'purchase_order_number',
        'supplier_sku',
        'supplier_products',
        'supplier_products_count',
        'purchase_orders_count',
    ];

    public function test_public_product_listing_excludes_supplier_fields(): void
    {
        $supplier = Supplier::factory()->china()->create([
            'name' => 'SECRET SUPPLIER CO',
            'email' => 'secret-supplier@example.com',
            'phone' => '0711111111',
            'contact_person' => 'Hidden Contact',
            'address' => 'Secret Warehouse Road',
            'notes' => 'Internal procurement note',
            'payment_terms' => 'Net 7 confidential',
        ]);

        $product = Product::factory()->fromChina()->create([
            'name' => 'Privacy List Phone',
            'supplier_id' => $supplier->id,
            'cost_price' => 12345.67,
        ]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.name', 'Privacy List Phone');

        $this->assertJsonHasNoForbiddenSupplierKeys($response->json());
        $this->assertStringNotContainsString('SECRET SUPPLIER', $response->getContent());
        $this->assertStringNotContainsString('secret-supplier@example.com', $response->getContent());
        $this->assertStringNotContainsString('Hidden Contact', $response->getContent());
    }

    public function test_public_product_detail_excludes_supplier_data(): void
    {
        $supplier = Supplier::factory()->china()->create([
            'name' => 'DETAIL LEAK SUPPLIER',
            'email' => 'detail-leak@example.com',
        ]);

        ['product' => $product] = CatalogCartFixture::purchasable(9999.99);
        $product->update([
            'slug' => 'privacy-detail-phone',
            'supplier_id' => $supplier->id,
            'cost_price' => 9999.99,
        ]);

        $response = $this->getJson('/api/v1/products/privacy-detail-phone');

        $response->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.slug', 'privacy-detail-phone');

        $this->assertJsonHasNoForbiddenSupplierKeys($response->json());
        $this->assertStringNotContainsString('DETAIL LEAK SUPPLIER', $response->getContent());
        $this->assertStringNotContainsString('detail-leak@example.com', $response->getContent());
    }

    public function test_unauthenticated_caller_cannot_retrieve_supplier_via_catalog(): void
    {
        $supplier = Supplier::factory()->create([
            'name' => 'UNAUTH SUPPLIER',
            'email' => 'unauth-supplier@example.com',
        ]);

        ['product' => $product] = CatalogCartFixture::purchasable(15000);
        $product->update([
            'slug' => 'unauth-catalog-item',
            'supplier_id' => $supplier->id,
            'cost_price' => 50,
        ]);

        $list = $this->getJson('/api/v1/products?include_supplier=true&with=supplier');
        $list->assertOk();
        $this->assertJsonHasNoForbiddenSupplierKeys($list->json());

        $detail = $this->getJson('/api/v1/products/unauth-catalog-item?include_supplier=true');
        $detail->assertOk();
        $this->assertJsonHasNoForbiddenSupplierKeys($detail->json());
        $this->assertStringNotContainsString('UNAUTH SUPPLIER', $detail->getContent());
    }

    public function test_customer_cannot_trigger_supplier_inclusion_via_query_parameters(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create(['name' => 'QUERY PARAM SUPPLIER']);
        ['product' => $product] = CatalogCartFixture::purchasable(12000);
        $product->update([
            'supplier_id' => $supplier->id,
            'cost_price' => 88.5,
        ]);

        Sanctum::actingAs($user);

        foreach ([
            '/api/v1/products?include_supplier=true',
            '/api/v1/products?supplier=1',
            '/api/v1/products/'.$product->slug.'?include_supplier=1&with[]=supplier',
        ] as $uri) {
            $response = $this->getJson($uri);
            $response->assertOk();
            $this->assertJsonHasNoForbiddenSupplierKeys($response->json());
            $this->assertStringNotContainsString('QUERY PARAM SUPPLIER', $response->getContent());
        }
    }

    public function test_product_with_eager_loaded_supplier_serializes_safely_on_customer_routes(): void
    {
        $supplier = Supplier::factory()->create([
            'name' => 'EAGER SUPPLIER',
            'email' => 'eager@example.com',
            'phone' => '0722222222',
        ]);

        ['product' => $product] = CatalogCartFixture::purchasable(22000);
        $product->update([
            'slug' => 'eager-supplier-product',
            'supplier_id' => $supplier->id,
            'cost_price' => 777,
        ]);

        $product->load('supplier');
        $this->assertTrue($product->relationLoaded('supplier'));

        $card = $this->resourceToArray(new \App\Http\Resources\CustomerProductCardResource($product));
        $detail = $this->resourceToArray(new \App\Http\Resources\CustomerProductDetailResource($product->load([
            'category',
            'brand',
            'images',
            'variants',
            'commerceChannel',
        ])));

        $this->assertJsonHasNoForbiddenSupplierKeys($card);
        $this->assertJsonHasNoForbiddenSupplierKeys($detail);

        $http = $this->getJson('/api/v1/products/eager-supplier-product');
        $http->assertOk();
        $this->assertJsonHasNoForbiddenSupplierKeys($http->json());
        $this->assertStringNotContainsString('EAGER SUPPLIER', $http->getContent());
    }

    public function test_customer_cart_excludes_supplier_information(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create([
            'name' => 'CART SUPPLIER',
            'email' => 'cart-supplier@example.com',
            'contact_person' => 'Cart Contact',
        ]);

        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(25000);
        $product->update([
            'supplier_id' => $supplier->id,
            'cost_price' => 10000,
        ]);

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
            'currency' => 'TZS',
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'price_snapshot' => 25000,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/cart');
        $response->assertOk()
            ->assertJsonPath('data.items.0.product.id', $product->id)
            ->assertJsonPath('data.items.0.unit_price', '25000.00');

        $this->assertJsonHasNoForbiddenSupplierKeys($response->json());
        $this->assertStringNotContainsString('CART SUPPLIER', $response->getContent());
        $this->assertStringNotContainsString('cart-supplier@example.com', $response->getContent());
    }

    public function test_cart_item_with_product_supplier_preloaded_still_excludes_supplier_fields(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create([
            'name' => 'PRELOADED CART SUPPLIER',
            'email' => 'preload-cart@example.com',
        ]);

        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(18000);
        $product->update(['supplier_id' => $supplier->id, 'cost_price' => 9000]);

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
        ]);

        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'unit_price' => 18000,
            'price_snapshot' => 18000,
        ]);

        $item->load(['product.supplier', 'variant']);
        $this->assertTrue($item->product->relationLoaded('supplier'));

        $serialized = $this->resourceToArray(new \App\Http\Resources\CartItemResource($item));
        $this->assertJsonHasNoForbiddenSupplierKeys($serialized);
        $this->assertArrayNotHasKey('supplier', $serialized['product'] ?? []);
        $this->assertArrayNotHasKey('supplier_id', $serialized['product'] ?? []);
        $this->assertArrayNotHasKey('cost_price', $serialized['product'] ?? []);

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/v1/cart');
        $response->assertOk();
        $this->assertJsonHasNoForbiddenSupplierKeys($response->json());
        $this->assertStringNotContainsString('PRELOADED CART SUPPLIER', $response->getContent());
    }

    public function test_customer_checkout_preview_and_session_exclude_supplier_information(): void
    {
        $user = User::factory()->create();
        DeliveryAddress::factory()->create(['user_id' => $user->id]);

        $supplier = Supplier::factory()->create([
            'name' => 'CHECKOUT SUPPLIER',
            'email' => 'checkout-supplier@example.com',
            'phone' => '0733333333',
        ]);

        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(20000);
        $product->update(['supplier_id' => $supplier->id, 'cost_price' => 8000]);

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
            'currency' => 'TZS',
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 20000,
            'price_snapshot' => 20000,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs($user);

        $preview = $this->getJson('/api/v1/checkout');
        $preview->assertOk();
        $this->assertJsonHasNoForbiddenSupplierKeys($preview->json());
        $this->assertStringNotContainsString('CHECKOUT SUPPLIER', $preview->getContent());

        $prepare = $this->postJson('/api/v1/checkout/prepare');
        $prepare->assertOk();
        $this->assertJsonHasNoForbiddenSupplierKeys($prepare->json());

        $start = $this->postJson('/api/v1/checkout/start');
        $start->assertCreated();
        $this->assertJsonHasNoForbiddenSupplierKeys($start->json());
        $this->assertStringNotContainsString('checkout-supplier@example.com', $start->getContent());

        $sessionId = $start->json('data.id');
        $session = $this->getJson("/api/v1/checkout/{$sessionId}");
        $session->assertOk();
        $this->assertJsonHasNoForbiddenSupplierKeys($session->json());
        $this->assertStringNotContainsString('CHECKOUT SUPPLIER', $session->getContent());
    }

    public function test_customer_order_history_and_detail_exclude_supplier_information(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create([
            'name' => 'ORDER SUPPLIER',
            'email' => 'order-supplier@example.com',
            'address' => 'Hidden PO Box 99',
        ]);

        $product = Product::factory()->fromDar()->create([
            'supplier_id' => $supplier->id,
            'cost_price' => 4000,
            'name' => 'Customer Visible Product',
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name_snapshot' => 'Customer Visible Product',
            'unit_price_snapshot' => 12000,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($user);

        $list = $this->getJson('/api/v1/orders');
        $list->assertOk();
        $this->assertJsonHasNoForbiddenSupplierKeys($list->json());
        $this->assertStringNotContainsString('ORDER SUPPLIER', $list->getContent());

        $detail = $this->getJson("/api/v1/orders/{$order->id}");
        $detail->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.items.0.product_name', 'Customer Visible Product');

        $this->assertJsonHasNoForbiddenSupplierKeys($detail->json());
        $this->assertStringNotContainsString('ORDER SUPPLIER', $detail->getContent());
        $this->assertStringNotContainsString('order-supplier@example.com', $detail->getContent());
        $this->assertStringNotContainsString('Hidden PO Box 99', $detail->getContent());
    }

    public function test_authorized_admin_resource_still_exposes_permitted_supplier_details(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $supplier = Supplier::factory()->create([
            'name' => 'Admin Visible Supplier',
            'email' => 'admin-supplier@example.com',
            'contact_person' => 'Admin Contact',
            'phone' => '0744444444',
            'payment_terms' => 'Net 30',
            'notes' => 'Admin-only notes',
        ]);

        $response = $this->getJson('/api/v1/admin/suppliers/'.$supplier->id);

        $response->assertOk()
            ->assertJsonPath('data.id', $supplier->id)
            ->assertJsonPath('data.name', 'Admin Visible Supplier')
            ->assertJsonPath('data.email', 'admin-supplier@example.com')
            ->assertJsonPath('data.contact_person', 'Admin Contact')
            ->assertJsonPath('data.phone', '0744444444')
            ->assertJsonPath('data.payment_terms', 'Net 30')
            ->assertJsonPath('data.notes', 'Admin-only notes');

        $list = $this->getJson('/api/v1/admin/suppliers');
        $list->assertOk();
        $this->assertStringContainsString('Admin Visible Supplier', $list->getContent());
    }

    public function test_customer_responses_exclude_internal_cost_and_procurement_fields(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create(['name' => 'COST LEAK SUPPLIER']);

        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(30000);
        $product->update([
            'supplier_id' => $supplier->id,
            'cost_price' => 11111.11,
            'slug' => 'cost-leak-product',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        foreach ([
            $this->getJson('/api/v1/products/cost-leak-product'),
            $this->getJson('/api/v1/cart'),
        ] as $response) {
            $response->assertSuccessful();
            $payload = $response->json();
            $this->assertJsonHasNoForbiddenSupplierKeys($payload);
            $this->assertDoesNotContainNumericCostLeak($payload, '11111.11');
        }
    }

    public function test_customer_returns_exclude_admin_notes_and_inventory_disposition(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'is_active' => true,
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_demo' => false,
        ]);
        $variant = \App\Models\ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Delivered,
            'paid_at' => now()->subDays(3),
        ]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price_snapshot' => 15000,
        ]);
        Fulfillment::factory()->create(['order_id' => $order->id]);
        Shipment::factory()->create([
            'order_id' => $order->id,
            'status' => ShipmentLifecycleStatus::Delivered,
            'delivered_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($user);
        $returnId = $this->postJson("/api/v1/orders/{$order->id}/returns", [
            'reason' => 'Privacy return',
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ])->assertCreated()->json('data.id');

        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);
        $returnItemId = ReturnItem::query()->where('return_request_id', $returnId)->value('id');

        $this->patchJson("/api/v1/admin/returns/{$returnId}/status", [
            'status' => 'approved',
            'admin_notes' => 'Internal only — do not expose to customer',
        ])->assertOk();

        $this->patchJson("/api/v1/admin/returns/{$returnId}/status", [
            'status' => 'inspection',
            'items' => [[
                'id' => $returnItemId,
                'inventory_disposition' => InventoryDisposition::Sellable->value,
            ]],
        ])->assertOk();

        Sanctum::actingAs($user);
        $response = $this->getJson("/api/v1/returns/{$returnId}");
        $response->assertOk();

        $encoded = json_encode($response->json('data'));
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('admin_notes', $encoded);
        $this->assertStringNotContainsString('approved_by', $encoded);
        $this->assertStringNotContainsString('inventory_disposition', $encoded);
        $this->assertStringNotContainsString('Internal only — do not expose to customer', $encoded);
    }

    /**
     * Fully resolve nested JsonResources to plain arrays.
     *
     * @return array<string, mixed>
     */
    private function resourceToArray(JsonResource $resource): array
    {
        $encoded = json_encode($resource);
        $this->assertIsString($encoded);
        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($encoded, true);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    /**
     * Recursively assert forbidden supplier/procurement keys are absent.
     *
     * @param  mixed  $payload
     */
    private function assertJsonHasNoForbiddenSupplierKeys(mixed $payload, string $path = 'root'): void
    {
        if (! is_array($payload)) {
            return;
        }

        $isList = array_is_list($payload);

        foreach ($payload as $key => $value) {
            $current = $isList ? "{$path}[{$key}]" : "{$path}.{$key}";

            if (! $isList && is_string($key) && in_array($key, self::FORBIDDEN_KEYS, true)) {
                $this->fail("Forbidden supplier/procurement key '{$key}' found at {$current}");
            }

            $this->assertJsonHasNoForbiddenSupplierKeys($value, $current);
        }
    }

    /**
     * @param  mixed  $payload
     */
    private function assertDoesNotContainNumericCostLeak(mixed $payload, string $forbiddenAmount): void
    {
        $encoded = json_encode($payload);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString($forbiddenAmount, $encoded);
        $this->assertStringNotContainsString('"cost_price"', $encoded);
        $this->assertStringNotContainsString('"purchase_cost"', $encoded);
        $this->assertStringNotContainsString('"landed_cost"', $encoded);
    }
}

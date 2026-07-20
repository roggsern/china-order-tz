<?php

namespace Tests\Feature\Commerce;

use App\Enums\CommerceChannelCode;
use App\Enums\DeliveryType;
use App\Enums\FulfillmentStrategy;
use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Commerce\CommerceChannelResolver;
use App\Services\Commerce\Strategies\ChinaCommerceStrategy;
use App\Services\Commerce\Strategies\TanzaniaCommerceStrategy;
use App\Services\Delivery\DeliveryTypeResolver;
use App\Services\Fulfillment\FulfillmentEngine;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceModeEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_channels_are_seeded_by_migration(): void
    {
        $this->assertDatabaseHas('commerce_channels', ['code' => 'CHINA_IMPORT']);
        $this->assertDatabaseHas('commerce_channels', ['code' => 'TZ_LOCAL']);
    }

    public function test_product_channel_assignment_syncs_fulfillment_source(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $tz = CommerceChannel::query()->where('code', 'TZ_LOCAL')->firstOrFail();
        $category = Category::factory()->create();
        $store = \App\Models\Store::query()->create([
            'code' => 'TZUR',
            'name' => 'Tzur Local',
            'slug' => 'tzur-local',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/admin/products', [
            'name' => 'Local Kettle',
            'category_id' => $category->id,
            'commerce_channel_id' => $tz->id,
            'store_id' => $store->id,
            'price' => 15000,
            'stock_quantity' => 5,
            'lifecycle_status' => 'active',
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.commerce_channel_id', $tz->id)
            ->assertJsonPath('data.fulfillment_source', 'buy_from_tz')
            ->assertJsonPath('data.commerce_channel.code', 'TZ_LOCAL');

        $productId = $response->json('data.id');
        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'commerce_channel_id' => $tz->id,
            'fulfillment_source' => 'buy_from_tz',
        ]);
    }

    public function test_mixed_cart_is_rejected(): void
    {
        $user = User::factory()->create();
        ['variant' => $chinaVariant] = CatalogCartFixture::purchasable(10000);
        Product::query()->whereKey($chinaVariant->product_id)->update([
            'commerce_channel_id' => CommerceChannel::query()->where('code', 'CHINA_IMPORT')->value('id'),
            'fulfillment_source' => 'imported_from_china',
        ]);

        ['variant' => $tzVariant] = CatalogCartFixture::purchasable(12000);
        Product::query()->whereKey($tzVariant->product_id)->update([
            'commerce_channel_id' => CommerceChannel::query()->where('code', 'TZ_LOCAL')->value('id'),
            'fulfillment_source' => 'buy_from_tz',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $chinaVariant->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $tzVariant->id,
            'quantity' => 1,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['cart']);
    }

    public function test_checkout_resolves_channel_and_order_snapshot_is_immutable(): void
    {
        $user = User::factory()->create();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(25000);

        $china = CommerceChannel::query()->where('code', 'CHINA_IMPORT')->firstOrFail();
        $tz = CommerceChannel::query()->where('code', 'TZ_LOCAL')->firstOrFail();

        $product->update([
            'commerce_channel_id' => $china->id,
            'fulfillment_source' => 'imported_from_china',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $sessionId = $this->postJson('/api/v1/checkout/start')
            ->assertCreated()
            ->json('data.id');

        $this->applyCheckoutShippingChoice($sessionId);

        $orderId = $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->json('data.id');

        $order = Order::query()->findOrFail($orderId);
        $this->assertSame($china->id, $order->commerce_channel_id);
        $this->assertSame('CHINA_IMPORT', $order->commerce_channel_snapshot['code'] ?? null);
        $this->assertSame('Imported From China', $order->commerce_channel_snapshot['customer_label'] ?? null);

        // Changing product channel must not rewrite historical order snapshot.
        $product->update([
            'commerce_channel_id' => $tz->id,
            'fulfillment_source' => 'buy_from_tz',
        ]);

        $order->refresh();
        $this->assertSame('CHINA_IMPORT', $order->commerce_channel_snapshot['code'] ?? null);
        $this->assertSame($china->id, $order->commerce_channel_id);

        $strategy = app(CommerceChannelResolver::class)->strategyForOrder($order);
        $this->assertInstanceOf(ChinaCommerceStrategy::class, $strategy);
    }

    public function test_china_and_tz_delivery_rules(): void
    {
        $resolver = app(DeliveryTypeResolver::class);

        $chinaOrder = $this->makeOrderOnChannel(CommerceChannelCode::ChinaImport);
        $tzOrder = $this->makeOrderOnChannel(CommerceChannelCode::TzLocal);

        $this->assertTrue($resolver->allows($chinaOrder, DeliveryType::CompanyShipping));
        $this->assertTrue($resolver->allows($chinaOrder, DeliveryType::CustomerAgent));
        $this->assertFalse($resolver->allows($chinaOrder, DeliveryType::SelfPickup));

        $this->assertTrue($resolver->allows($tzOrder, DeliveryType::SelfPickup));
        $this->assertTrue($resolver->allows($tzOrder, DeliveryType::NegotiatedDelivery));
        $this->assertFalse($resolver->allows($tzOrder, DeliveryType::CompanyShipping));
    }

    public function test_china_and_tz_fulfillment_workflows(): void
    {
        $chinaOrder = $this->makePaidOrderOnChannel(CommerceChannelCode::ChinaImport);
        $tzOrder = $this->makePaidOrderOnChannel(CommerceChannelCode::TzLocal);

        $engine = app(FulfillmentEngine::class);

        $chinaFulfillment = $engine->createForOrder($chinaOrder);
        $tzFulfillment = $engine->createForOrder($tzOrder);

        $this->assertSame(FulfillmentStrategy::China, $chinaFulfillment->strategy);
        $this->assertSame(FulfillmentStrategy::Local, $tzFulfillment->strategy);

        $this->assertInstanceOf(
            ChinaCommerceStrategy::class,
            app(CommerceChannelResolver::class)->strategyForOrder($chinaOrder),
        );
        $this->assertInstanceOf(
            TanzaniaCommerceStrategy::class,
            app(CommerceChannelResolver::class)->strategyForOrder($tzOrder),
        );
    }

    public function test_admin_commerce_channels_api_requires_admin(): void
    {
        $this->getJson('/api/v1/admin/commerce-channels')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/commerce-channels')->assertUnauthorized();

        Sanctum::actingAs(Admin::factory()->create());

        $list = $this->getJson('/api/v1/admin/commerce-channels')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data');

        $this->assertCount(2, $list);
        $codes = collect($list)->pluck('code')->sort()->values()->all();
        $this->assertSame(['CHINA_IMPORT', 'TZ_LOCAL'], $codes);

        $china = CommerceChannel::query()->where('code', 'CHINA_IMPORT')->firstOrFail();
        $this->getJson("/api/v1/admin/commerce-channels/{$china->id}")
            ->assertOk()
            ->assertJsonPath('data.code', 'CHINA_IMPORT')
            ->assertJsonPath('data.customer_label', 'Imported From China');
    }

    private function makeOrderOnChannel(CommerceChannelCode $code): Order
    {
        $channel = CommerceChannel::query()->where('code', $code->value)->firstOrFail();
        $product = Product::factory()->create([
            'commerce_channel_id' => $channel->id,
            'fulfillment_source' => $code->fulfillmentSource(),
        ]);

        $order = Order::factory()->create([
            'commerce_channel_id' => $channel->id,
            'commerce_channel_snapshot' => app(CommerceChannelResolver::class)->snapshot($channel),
            'status' => OrderStatus::PendingPayment,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'unit_price' => 10000,
            'total_price' => 10000,
            'line_total' => 10000,
        ]);

        return $order->fresh(['items.product.commerceChannel', 'commerceChannel']);
    }

    private function makePaidOrderOnChannel(CommerceChannelCode $code): Order
    {
        $order = $this->makeOrderOnChannel($code);
        $order->update([
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);

        return $order->fresh(['items.product.commerceChannel', 'commerceChannel']);
    }
}

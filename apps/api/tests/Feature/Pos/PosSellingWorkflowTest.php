<?php

namespace Tests\Feature\Pos;

use App\Enums\CommerceChannelCode;
use App\Enums\PosPaymentHandler;
use App\Enums\PromotionDiscountType;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Enums\SalesOrigin;
use App\Enums\VariantPriceType;
use App\Models\Admin;
use App\Models\CommerceChannel;
use App\Models\PaymentMethodDefinition;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use App\Services\Stores\StoreAssignmentService;
use App\Services\Stores\StoreService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosSellingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private StoreService $stores;

    private StoreAssignmentService $assignments;

    private CommerceChannel $tz;

    private CommerceChannel $china;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->stores = app(StoreService::class);
        $this->assignments = app(StoreAssignmentService::class);

        $this->tz = CommerceChannel::query()->updateOrCreate(
            ['code' => CommerceChannelCode::TzLocal->value],
            ['name' => 'Buy From TZ', 'description' => 'Local', 'is_active' => true],
        );
        $this->china = CommerceChannel::query()->updateOrCreate(
            ['code' => CommerceChannelCode::ChinaImport->value],
            ['name' => 'China Import', 'description' => 'Import', 'is_active' => true],
        );

        PaymentMethodDefinition::query()->updateOrCreate(
            ['code' => 'CASH'],
            [
                'name' => 'Cash',
                'is_active' => true,
                'sort_order' => 1,
                'config' => [
                    'handler' => PosPaymentHandler::CashWithChange->value,
                    'pos_enabled' => true,
                ],
            ],
        );
        PaymentMethodDefinition::query()->updateOrCreate(
            ['code' => 'MPESA_LIPA'],
            [
                'name' => 'M-Pesa Lipa',
                'is_active' => true,
                'sort_order' => 2,
                'config' => [
                    'handler' => PosPaymentHandler::ManualConfirm->value,
                    'pos_enabled' => true,
                ],
            ],
        );
    }

    private function seedSellable(string $storeId, float $price = 50000, int $stock = 12, array $productAttrs = []): array
    {
        $suffix = substr(str_replace('-', '', $storeId), 0, 8);
        $variantSku = $productAttrs['variant_sku'] ?? "ZD-{$suffix}";
        $barcode = $productAttrs['barcode'] ?? ('6281'.str_pad(substr(preg_replace('/\D/', '', $storeId) ?: '1', 0, 9), 9, '0'));
        unset($productAttrs['variant_sku'], $productAttrs['barcode']);

        $product = Product::factory()->create(array_merge([
            'store_id' => $storeId,
            'commerce_channel_id' => $this->tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'price' => $price,
            'is_active' => true,
            'is_demo' => false,
            'name' => 'Black Dress',
            'sku' => "ZD-ROOT-{$suffix}",
        ], $productAttrs));

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Size M',
            'sku' => $variantSku,
            'barcode' => $barcode,
            'price' => $price,
            'is_active' => true,
        ]);

        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => $price,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);

        $store = \App\Models\Store::query()->findOrFail($storeId);
        $location = $store->defaultInventoryLocation;

        $inventory = VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'inventory_location_id' => $location->id,
            'warehouse_code' => $location->code,
            'on_hand' => $stock,
            'reserved' => 0,
            'reorder_level' => 1,
            'safety_stock' => 0,
            'is_active' => true,
        ]);

        return compact('product', 'variant', 'inventory', 'location');
    }

    private function openSession(Admin $cashier, \App\Models\Store $store): void
    {
        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $store->id,
            'terminal_id' => $store->terminals()->firstOrFail()->id,
            'opening_float' => 100000,
        ])->assertCreated();
    }

    public function test_store_isolation_zion_cannot_sell_tzur_product(): void
    {
        $zion = $this->stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $tzur = $this->stores->create(['code' => 'TZUR', 'name' => 'Tzur']);
        $super = Admin::factory()->superAdmin()->create();
        $cashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
        ]);
        $this->assignments->assign($cashier, $zion, $super);

        $tzurSku = $this->seedSellable($tzur->id);
        $this->openSession($cashier, $zion);

        $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [[
                'product_id' => $tzurSku['product']->id,
                'product_variant_id' => $tzurSku['variant']->id,
                'quantity' => 1,
            ]],
            'payment_method' => 'CASH',
            'amount_received' => 50000,
        ])->assertStatus(422);
    }

    public function test_master_cashier_can_switch_assigned_stores(): void
    {
        $zion = $this->stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $peachy = $this->stores->create(['code' => 'PEACHY', 'name' => 'Peachy']);
        $super = Admin::factory()->superAdmin()->create();
        $master = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'master_cashier')->value('id'),
            'is_super_admin' => false,
        ]);
        $this->assignments->assign($master, $zion, $super);
        $this->assignments->assign($master, $peachy, $super);

        Sanctum::actingAs($master);
        $this->getJson('/api/v1/admin/pos/my-stores')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $peachy->id,
            'terminal_id' => $peachy->terminals()->firstOrFail()->id,
            'opening_float' => 50000,
        ])->assertCreated()->assertJsonPath('data.store_id', $peachy->id);
    }

    public function test_catalog_only_active_store_products_and_search(): void
    {
        $zion = $this->stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $tzur = $this->stores->create(['code' => 'TZUR', 'name' => 'Tzur']);
        $super = Admin::factory()->superAdmin()->create();
        $cashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
        ]);
        $this->assignments->assign($cashier, $zion, $super);

        $zionSku = $this->seedSellable($zion->id, 50000, 12, ['variant_sku' => 'ZD-001', 'barcode' => '6281001234567']);
        $this->seedSellable($tzur->id, 20000, 5, [
            'name' => 'Gold Ring',
            'sku' => 'TZ-RING',
            'variant_sku' => 'TZ-RING-M',
            'barcode' => '6281999999999',
        ]);

        $chinaProduct = Product::factory()->create([
            'store_id' => $zion->id,
            'commerce_channel_id' => $this->china->id,
            'fulfillment_source' => CommerceChannelCode::ChinaImport->fulfillmentSource(),
            'is_active' => true,
            'is_demo' => false,
            'name' => 'Import Phone',
        ]);
        ProductVariant::factory()->create(['product_id' => $chinaProduct->id, 'sku' => 'CN-1']);

        $this->openSession($cashier, $zion);

        $catalog = $this->getJson('/api/v1/admin/pos/catalog')->assertOk();
        $names = collect($catalog->json('data'))->pluck('product_name');
        $this->assertTrue($names->contains('Black Dress'));
        $this->assertFalse($names->contains('Gold Ring'));
        $this->assertFalse($names->contains('Import Phone'));

        $search = $this->getJson('/api/v1/admin/pos/catalog?q='.$zionSku['variant']->sku)->assertOk();
        $this->assertSame($zionSku['variant']->sku, $search->json('data.0.variant_sku'));
        $this->assertSame('50000.00', $search->json('data.0.unit_price'));
        $this->assertSame(12, $search->json('data.0.available_stock'));
    }

    public function test_walk_in_sale_and_inventory_decrement(): void
    {
        $zion = $this->stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $super = Admin::factory()->superAdmin()->create();
        $cashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
        ]);
        $this->assignments->assign($cashier, $zion, $super);
        $sku = $this->seedSellable($zion->id, 50000, 12);

        $this->openSession($cashier, $zion);

        $sale = $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [[
                'product_id' => $sku['product']->id,
                'product_variant_id' => $sku['variant']->id,
                'quantity' => 2,
            ]],
            'payment_method' => 'CASH',
            'amount_received' => 100000,
        ])->assertCreated();

        $sale->assertJsonPath('data.order.user_id', null)
            ->assertJsonPath('data.order.sales_origin', SalesOrigin::Pos->value)
            ->assertJsonPath('data.order.total', '100000.00')
            ->assertJsonPath('data.change', '0.00');

        $this->assertSame(10, $sku['inventory']->fresh()->on_hand);
        $this->assertSame($zion->defaultInventoryLocation->code, $sku['inventory']->fresh()->warehouse_code);
    }

    public function test_crm_customer_attached_to_sale(): void
    {
        $zion = $this->stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $super = Admin::factory()->superAdmin()->create();
        $cashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
        ]);
        $this->assignments->assign($cashier, $zion, $super);
        $sku = $this->seedSellable($zion->id, 10000, 5);
        $customer = User::factory()->create(['email' => 'buyer@example.com']);

        $this->openSession($cashier, $zion);

        $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [[
                'product_id' => $sku['product']->id,
                'product_variant_id' => $sku['variant']->id,
                'quantity' => 1,
            ]],
            'payment_method' => 'MPESA_LIPA',
            'manual_confirmed' => true,
            'customer_id' => $customer->id,
        ])->assertCreated()
            ->assertJsonPath('data.order.user_id', $customer->id);
    }

    public function test_pricing_uses_variant_price_engine_amount(): void
    {
        $zion = $this->stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $super = Admin::factory()->superAdmin()->create();
        $cashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
        ]);
        $this->assignments->assign($cashier, $zion, $super);
        $sku = $this->seedSellable($zion->id, 77777, 3);

        // Legacy column differs — POS must prefer VariantPrice.
        $sku['variant']->update(['price' => 11111]);

        $this->openSession($cashier, $zion);
        $quote = $this->postJson('/api/v1/admin/pos/quote', [
            'items' => [[
                'product_id' => $sku['product']->id,
                'product_variant_id' => $sku['variant']->id,
                'quantity' => 1,
            ]],
        ])->assertOk();

        $this->assertSame('77777.00', $quote->json('data.lines.0.unit_price'));
        $this->assertSame('77777.00', $quote->json('data.grand_total'));
    }

    public function test_promotion_code_applies_discount(): void
    {
        $zion = $this->stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $super = Admin::factory()->superAdmin()->create();
        $cashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
        ]);
        $this->assignments->assign($cashier, $zion, $super);
        $sku = $this->seedSellable($zion->id, 100000, 5);

        Promotion::query()->create([
            'name' => 'POS Ten Off',
            'code' => 'POS10',
            'type' => PromotionType::Coupon,
            'discount_type' => PromotionDiscountType::Percentage,
            'value' => 10,
            'currency' => 'TZS',
            'status' => PromotionStatus::Active,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $this->openSession($cashier, $zion);
        $quote = $this->postJson('/api/v1/admin/pos/quote', [
            'items' => [[
                'product_id' => $sku['product']->id,
                'product_variant_id' => $sku['variant']->id,
                'quantity' => 1,
            ]],
            'promotion_code' => 'POS10',
        ])->assertOk();

        $this->assertSame('10000.00', $quote->json('data.discount_total'));
        $this->assertSame('90000.00', $quote->json('data.grand_total'));
    }

    public function test_china_import_rejected(): void
    {
        $zion = $this->stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $super = Admin::factory()->superAdmin()->create();
        $cashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
        ]);
        $this->assignments->assign($cashier, $zion, $super);

        $product = Product::factory()->create([
            'store_id' => $zion->id,
            'commerce_channel_id' => $this->china->id,
            'fulfillment_source' => CommerceChannelCode::ChinaImport->fulfillmentSource(),
            'is_demo' => false,
            'is_active' => true,
        ]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 1000]);
        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 1000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'inventory_location_id' => $zion->defaultInventoryLocation->id,
            'warehouse_code' => $zion->defaultInventoryLocation->code,
            'on_hand' => 5,
            'reserved' => 0,
            'is_active' => true,
        ]);

        $this->openSession($cashier, $zion);
        $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [[
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ]],
            'payment_method' => 'CASH',
            'amount_received' => 1000,
        ])->assertStatus(422);
    }

    public function test_show_sale_endpoint(): void
    {
        $zion = $this->stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $super = Admin::factory()->superAdmin()->create();
        $cashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
        ]);
        $this->assignments->assign($cashier, $zion, $super);
        $sku = $this->seedSellable($zion->id, 25000, 4);

        $this->openSession($cashier, $zion);
        $sale = $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [[
                'product_id' => $sku['product']->id,
                'product_variant_id' => $sku['variant']->id,
                'quantity' => 1,
            ]],
            'payment_method' => 'CASH',
            'amount_received' => 25000,
        ])->assertCreated();

        $orderId = $sale->json('data.order.id');
        $this->getJson("/api/v1/admin/pos/sales/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.id', $orderId)
            ->assertJsonPath('data.sales_origin', SalesOrigin::Pos->value);
    }
}

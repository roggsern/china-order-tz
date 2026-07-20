<?php

namespace Tests\Feature\Pos;

use App\Enums\CommerceChannelCode;
use App\Enums\PosPaymentHandler;
use App\Enums\SalesOrigin;
use App\Models\Admin;
use App\Models\CommerceChannel;
use App\Models\PaymentMethodDefinition;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\VariantInventory;
use App\Services\Stores\StoreAssignmentService;
use App\Services\Stores\StoreService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosPlatformTest extends TestCase
{
    use RefreshDatabase;

    private StoreService $stores;

    private StoreAssignmentService $assignments;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->stores = app(StoreService::class);
        $this->assignments = app(StoreAssignmentService::class);

        CommerceChannel::query()->updateOrCreate(
            ['code' => CommerceChannelCode::TzLocal->value],
            ['name' => 'Buy From TZ', 'description' => 'Local', 'is_active' => true],
        );
        CommerceChannel::query()->updateOrCreate(
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
    }

    public function test_super_admin_can_create_store_with_default_location_and_terminal(): void
    {
        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/admin/stores', [
            'code' => 'ZION',
            'name' => 'Zion Mode',
            'theme_color' => '#1F4B3A',
            'description' => 'Fashion',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'ZION')
            ->assertJsonPath('data.theme_color', '#1F4B3A');

        $store = Store::query()->where('code', 'ZION')->firstOrFail();
        $this->assertDatabaseHas('inventory_locations', [
            'store_id' => $store->id,
            'code' => 'ZION',
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('pos_terminals', [
            'store_id' => $store->id,
            'code' => 'T1',
        ]);
    }

    public function test_store_cashier_cannot_access_unassigned_store(): void
    {
        $zion = $this->stores->create(['code' => 'ZION', 'name' => 'Zion Mode']);
        $peachy = $this->stores->create(['code' => 'PEACHY', 'name' => 'Peachy']);

        $role = Role::query()->where('slug', 'store_cashier')->firstOrFail();
        $cashier = Admin::factory()->create(['role_id' => $role->id, 'is_super_admin' => false]);
        $super = Admin::factory()->superAdmin()->create();

        $this->assignments->assign($cashier, $zion, $super);

        Sanctum::actingAs($cashier);
        $this->getJson('/api/v1/admin/stores/'.$peachy->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['store_id']);
    }

    public function test_temporary_assignment_allows_then_blocks_after_revoke(): void
    {
        $zion = $this->stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $tzur = $this->stores->create(['code' => 'TZUR', 'name' => 'Tzur']);
        $role = Role::query()->where('slug', 'store_cashier')->firstOrFail();
        $cashier = Admin::factory()->create(['role_id' => $role->id, 'is_super_admin' => false]);
        $super = Admin::factory()->superAdmin()->create();

        $this->assignments->assign($cashier, $zion, $super);
        $this->assignments->assign($cashier, $tzur, $super);

        Sanctum::actingAs($cashier);
        $this->getJson('/api/v1/admin/pos/my-stores')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assignments->revoke($cashier, $tzur, $super);

        $this->getJson('/api/v1/admin/pos/my-stores')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'ZION');
    }

    public function test_pos_cash_sale_walk_in_without_customer(): void
    {
        $store = $this->stores->create(['code' => 'ZION', 'name' => 'Zion Mode']);
        $location = $store->defaultInventoryLocation;
        $terminal = $store->terminals()->firstOrFail();
        $tz = CommerceChannel::query()->where('code', CommerceChannelCode::TzLocal->value)->firstOrFail();

        $super = Admin::factory()->superAdmin()->create();
        $role = Role::query()->where('slug', 'store_cashier')->firstOrFail();
        $cashier = Admin::factory()->create(['role_id' => $role->id, 'is_super_admin' => false]);
        $this->assignments->assign($cashier, $store, $super);

        $product = Product::factory()->create([
            'store_id' => $store->id,
            'commerce_channel_id' => $tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'price' => 10000,
            'is_active' => true,
            'is_demo' => false,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 10000,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'inventory_location_id' => $location->id,
            'warehouse_code' => $location->code,
            'on_hand' => 5,
            'reserved' => 0,
            'reorder_level' => 1,
            'safety_stock' => 0,
            'is_active' => true,
        ]);

        Sanctum::actingAs($cashier);

        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $store->id,
            'terminal_id' => $terminal->id,
            'opening_float' => 50000,
        ])->assertCreated();

        $sale = $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => 2,
                ],
            ],
            'payment_method' => 'CASH',
            'amount_received' => 25000,
        ]);

        $sale->assertCreated()
            ->assertJsonPath('data.change', '5000.00')
            ->assertJsonPath('data.order.sales_origin', SalesOrigin::Pos->value)
            ->assertJsonPath('data.order.user_id', null)
            ->assertJsonPath('data.order.store_id', $store->id);

        $this->assertDatabaseHas('pos_receipts', [
            'order_id' => $sale->json('data.order.id'),
            'store_id' => $store->id,
        ]);

        $this->assertSame(3, VariantInventory::query()->whereKey(
            VariantInventory::query()->where('product_variant_id', $variant->id)->value('id')
        )->value('on_hand'));
    }

    public function test_pos_rejects_china_import_product(): void
    {
        $store = $this->stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $terminal = $store->terminals()->firstOrFail();
        $china = CommerceChannel::query()->where('code', CommerceChannelCode::ChinaImport->value)->firstOrFail();

        $super = Admin::factory()->superAdmin()->create();
        $cashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
        ]);
        $this->assignments->assign($cashier, $store, $super);

        $product = Product::factory()->create([
            'store_id' => $store->id,
            'commerce_channel_id' => $china->id,
            'fulfillment_source' => CommerceChannelCode::ChinaImport->fulfillmentSource(),
            'is_demo' => false,
        ]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 1000]);
        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'inventory_location_id' => $store->defaultInventoryLocation->id,
            'warehouse_code' => $store->defaultInventoryLocation->code,
            'on_hand' => 10,
            'reserved' => 0,
            'is_active' => true,
        ]);

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'terminal_id' => $terminal->id,
            'opening_float' => 0,
        ])->assertCreated();

        $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [
                ['product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 1],
            ],
            'payment_method' => 'CASH',
            'amount_received' => 1000,
        ])->assertStatus(422);
    }
}

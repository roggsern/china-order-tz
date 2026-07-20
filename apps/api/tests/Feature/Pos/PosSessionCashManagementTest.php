<?php

namespace Tests\Feature\Pos;

use App\Enums\ActivityEventType;
use App\Enums\CommerceChannelCode;
use App\Enums\PosPaymentHandler;
use App\Enums\PosSessionVarianceType;
use App\Enums\VariantPriceType;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\CommerceChannel;
use App\Models\PaymentMethodDefinition;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use App\Services\Stores\StoreAssignmentService;
use App\Services\Stores\StoreService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosSessionCashManagementTest extends TestCase
{
    use RefreshDatabase;

    private StoreService $stores;

    private StoreAssignmentService $assignments;

    private CommerceChannel $tz;

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
        PaymentMethodDefinition::query()->updateOrCreate(
            ['code' => 'NMB_BANK'],
            [
                'name' => 'NMB Bank',
                'is_active' => true,
                'sort_order' => 3,
                'config' => [
                    'handler' => PosPaymentHandler::ManualConfirm->value,
                    'pos_enabled' => true,
                ],
            ],
        );
    }

    private function cashierFor(\App\Models\Store $store): Admin
    {
        $super = Admin::factory()->superAdmin()->create();
        $cashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
        ]);
        $this->assignments->assign($cashier, $store, $super);

        return $cashier;
    }

    private function seedSellable(string $storeId, float $price = 50000, int $stock = 20): array
    {
        $product = Product::factory()->create([
            'store_id' => $storeId,
            'commerce_channel_id' => $this->tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'price' => $price,
            'is_active' => true,
            'is_demo' => false,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
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
            'is_active' => true,
        ]);

        return compact('product', 'variant', 'inventory');
    }

    public function test_open_session_requires_opening_float_and_audits(): void
    {
        $store = $this->stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $cashier = $this->cashierFor($store);
        Sanctum::actingAs($cashier);

        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $store->id,
            'terminal_id' => $store->terminals()->firstOrFail()->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['opening_float']);

        $opened = $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $store->id,
            'terminal_id' => $store->terminals()->firstOrFail()->id,
            'opening_float' => 100000,
        ])->assertCreated();

        $opened->assertJsonPath('data.opening_float', '100000.00')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.summary.expected_cash', '100000.00');

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::PosSessionOpened->value,
            'subject_id' => $opened->json('data.id'),
        ]);
    }

    public function test_duplicate_session_on_cashier_and_terminal_rejected(): void
    {
        $store = $this->stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $super = Admin::factory()->superAdmin()->create();
        $cashierA = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
        ]);
        $cashierB = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
        ]);
        $this->assignments->assign($cashierA, $store, $super);
        $this->assignments->assign($cashierB, $store, $super);
        $terminal = $store->terminals()->firstOrFail();

        Sanctum::actingAs($cashierA);
        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $store->id,
            'terminal_id' => $terminal->id,
            'opening_float' => 50000,
        ])->assertCreated();

        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $store->id,
            'terminal_id' => $terminal->id,
            'opening_float' => 10000,
        ])->assertStatus(422);

        Sanctum::actingAs($cashierB);
        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $store->id,
            'terminal_id' => $terminal->id,
            'opening_float' => 20000,
        ])->assertStatus(422)->assertJsonValidationErrors(['terminal_id']);
    }

    public function test_sale_without_session_rejected(): void
    {
        $store = $this->stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $cashier = $this->cashierFor($store);
        $sku = $this->seedSellable($store->id);

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [[
                'product_id' => $sku['product']->id,
                'product_variant_id' => $sku['variant']->id,
                'quantity' => 1,
            ]],
            'payment_method' => 'CASH',
            'amount_received' => 50000,
        ])->assertStatus(422);
    }

    public function test_expected_cash_payment_breakdown_and_variance(): void
    {
        $store = $this->stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $cashier = $this->cashierFor($store);
        $sku = $this->seedSellable($store->id, 50000, 20);

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $store->id,
            'terminal_id' => $store->terminals()->firstOrFail()->id,
            'opening_float' => 100000,
        ])->assertCreated();

        $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [[
                'product_id' => $sku['product']->id,
                'product_variant_id' => $sku['variant']->id,
                'quantity' => 2,
            ]],
            'payment_method' => 'CASH',
            'amount_received' => 100000,
        ])->assertCreated();

        $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [[
                'product_id' => $sku['product']->id,
                'product_variant_id' => $sku['variant']->id,
                'quantity' => 1,
            ]],
            'payment_method' => 'MPESA_LIPA',
            'manual_confirmed' => true,
        ])->assertCreated();

        $dash = $this->getJson('/api/v1/admin/pos/dashboard')->assertOk();
        $dash->assertJsonPath('data.summary.opening_float', '100000.00')
            ->assertJsonPath('data.summary.cash_sales', '100000.00')
            ->assertJsonPath('data.summary.expected_cash', '200000.00')
            ->assertJsonPath('data.summary.transaction_count', 2)
            ->assertJsonPath('data.summary.total_sales', '150000.00');

        $breakdown = collect($dash->json('data.summary.payment_breakdown'));
        $this->assertSame('100000.00', $breakdown->firstWhere('code', 'CASH')['amount']);
        $this->assertSame('50000.00', $breakdown->firstWhere('code', 'MPESA_LIPA')['amount']);
        $this->assertSame('0.00', $breakdown->firstWhere('code', 'NMB_BANK')['amount']);

        // Short by 2000
        $closed = $this->postJson('/api/v1/admin/pos/sessions/close', [
            'closing_cash' => 198000,
            'variance_reason' => 'customer_change_mistake',
            'closing_notes' => 'Change given incorrectly',
        ])->assertOk();

        $closed->assertJsonPath('data.expected_cash', '200000.00')
            ->assertJsonPath('data.closing_cash', '198000.00')
            ->assertJsonPath('data.variance_amount', '-2000.00')
            ->assertJsonPath('data.variance_type', PosSessionVarianceType::Short->value)
            ->assertJsonPath('data.status', 'closed');

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::PosSessionClosed->value,
            'subject_id' => $closed->json('data.id'),
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::PosVarianceDetected->value,
            'subject_id' => $closed->json('data.id'),
        ]);
    }

    public function test_balanced_close_does_not_emit_variance_event(): void
    {
        $store = $this->stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $cashier = $this->cashierFor($store);

        Sanctum::actingAs($cashier);
        $open = $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $store->id,
            'terminal_id' => $store->terminals()->firstOrFail()->id,
            'opening_float' => 75000,
        ])->assertCreated();

        $sessionId = $open->json('data.id');

        $this->postJson('/api/v1/admin/pos/sessions/close', [
            'closing_cash' => 75000,
        ])->assertOk()
            ->assertJsonPath('data.variance_type', PosSessionVarianceType::Balanced->value)
            ->assertJsonPath('data.variance_amount', '0.00');

        $this->assertSame(
            0,
            ActivityLog::query()
                ->where('event_type', ActivityEventType::PosVarianceDetected->value)
                ->where('subject_id', $sessionId)
                ->count()
        );
    }

    public function test_float_update_audits_and_manager_store_isolation(): void
    {
        $zion = $this->stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $tzur = $this->stores->create(['code' => 'TZUR', 'name' => 'Tzur']);
        $super = Admin::factory()->superAdmin()->create();

        $master = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'master_cashier')->value('id'),
            'is_super_admin' => false,
        ]);
        $zionCashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
        ]);
        $tzurCashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
        ]);

        $this->assignments->assign($master, $zion, $super);
        $this->assignments->assign($zionCashier, $zion, $super);
        $this->assignments->assign($tzurCashier, $tzur, $super);

        Sanctum::actingAs($zionCashier);
        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $zion->id,
            'terminal_id' => $zion->terminals()->firstOrFail()->id,
            'opening_float' => 10000,
        ])->assertCreated();

        $this->patchJson('/api/v1/admin/pos/sessions/float', [
            'opening_float' => 25000,
        ])->assertOk()->assertJsonPath('data.opening_float', '25000.00');

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::PosFloatUpdated->value,
        ]);

        Sanctum::actingAs($tzurCashier);
        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $tzur->id,
            'terminal_id' => $tzur->terminals()->firstOrFail()->id,
            'opening_float' => 5000,
        ])->assertCreated();

        Sanctum::actingAs($master);
        $list = $this->getJson('/api/v1/admin/pos/sessions')->assertOk();
        $storeCodes = collect($list->json('data'))->pluck('store.code');
        $this->assertTrue($storeCodes->contains('ZION'));
        $this->assertFalse($storeCodes->contains('TZUR'));
    }

    public function test_cannot_modify_closed_session_float(): void
    {
        $store = $this->stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $cashier = $this->cashierFor($store);
        Sanctum::actingAs($cashier);

        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $store->id,
            'terminal_id' => $store->terminals()->firstOrFail()->id,
            'opening_float' => 10000,
        ])->assertCreated();

        $this->postJson('/api/v1/admin/pos/sessions/close', [
            'closing_cash' => 10000,
        ])->assertOk();

        $this->patchJson('/api/v1/admin/pos/sessions/float', [
            'opening_float' => 20000,
        ])->assertStatus(422);
    }
}

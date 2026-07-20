<?php

namespace Tests\Feature\Loyalty;

use App\Enums\ActivityEventType;
use App\Enums\CommerceChannelCode;
use App\Enums\LoyaltyLedgerType;
use App\Enums\OrderStatus;
use App\Enums\PosPaymentHandler;
use App\Enums\PromotionDiscountType;
use App\Enums\SalesOrigin;
use App\Enums\VariantPriceType;
use App\Events\Audit\PaymentConfirmed;
use App\Models\Admin;
use App\Models\CommerceChannel;
use App\Models\CustomerProfile;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyLedgerEntry;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyTier;
use App\Models\Order;
use App\Models\PaymentMethodDefinition;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use App\Services\Crm\CustomerProfileService;
use App\Services\Loyalty\LoyaltyEngine;
use App\Services\Stores\StoreAssignmentService;
use App\Services\Stores\StoreService;
use Database\Seeders\LoyaltySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoyaltyPlatformTest extends TestCase
{
    use RefreshDatabase;

    private StoreService $stores;

    private StoreAssignmentService $assignments;

    private CommerceChannel $tz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(LoyaltySeeder::class);
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
                'config' => ['handler' => PosPaymentHandler::CashWithChange->value, 'pos_enabled' => true],
            ],
        );
    }

    public function test_customer_loyalty_profile_creation_and_authorization(): void
    {
        $this->getJson('/api/v1/loyalty/profile')->assertUnauthorized();
        $this->getJson('/api/v1/admin/loyalty/dashboard')->assertUnauthorized();

        $user = $this->customerUser();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/loyalty/profile')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('loyalty_accounts', [
            'customer_profile_id' => CustomerProfile::query()->where('user_id', $user->id)->value('id'),
        ]);

        Sanctum::actingAs(User::factory()->create(['is_active' => true]));
        $this->getJson('/api/v1/admin/loyalty/dashboard')->assertUnauthorized();

        $admin = Admin::factory()->create(['is_active' => true]);
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/loyalty/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_points_earned_after_payment_and_cancelled_order_earns_none(): void
    {
        $user = $this->customerUser();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'total' => 10000,
            'sales_origin' => SalesOrigin::Online,
        ]);

        event(PaymentConfirmed::fromOrder($order));

        $account = LoyaltyAccount::query()
            ->whereHas('profile', fn ($q) => $q->where('user_id', $user->id))
            ->first();

        $this->assertNotNull($account);
        // 10000 / 1000 * 10 = 100 points
        $this->assertSame(100, (int) $account->points_balance);
        $this->assertDatabaseHas('loyalty_ledger_entries', [
            'loyalty_account_id' => $account->id,
            'entry_type' => LoyaltyLedgerType::Earn->value,
            'order_id' => $order->id,
            'points' => 100,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::LoyaltyPointsEarned->value,
        ]);

        // Idempotent
        event(PaymentConfirmed::fromOrder($order));
        $this->assertSame(100, (int) $account->fresh()->points_balance);

        $cancelled = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Cancelled,
            'total' => 50000,
        ]);
        event(PaymentConfirmed::fromOrder($cancelled));
        $this->assertSame(100, (int) $account->fresh()->points_balance);
        $this->assertDatabaseMissing('loyalty_ledger_entries', [
            'order_id' => $cancelled->id,
            'entry_type' => LoyaltyLedgerType::Earn->value,
        ]);
    }

    public function test_point_ledger_immutable_and_manual_adjust_requires_reason(): void
    {
        $user = $this->customerUser();
        $account = app(LoyaltyEngine::class)->ensureAccountForUser($user);
        $this->assertNotNull($account);

        $admin = Admin::factory()->create(['is_active' => true]);
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/loyalty/customers/{$account->id}/adjust", [
            'points' => 25,
            'reason' => '',
        ])->assertStatus(422);

        $this->postJson("/api/v1/admin/loyalty/customers/{$account->id}/adjust", [
            'points' => 25,
            'reason' => 'Goodwill credit',
        ])->assertOk()
            ->assertJsonPath('data.account.points_balance', 25);

        $entry = LoyaltyLedgerEntry::query()->where('loyalty_account_id', $account->id)->first();
        $this->assertNotNull($entry);
        $this->assertFalse(array_key_exists('updated_at', $entry->getAttributes()));

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::LoyaltyPointsAdjusted->value,
        ]);
    }

    public function test_redemption_creates_promotion_coupon_and_ledger(): void
    {
        $user = $this->customerUser();
        $engine = app(LoyaltyEngine::class);
        $account = $engine->ensureAccountForUser($user);
        $admin = Admin::factory()->create(['is_active' => true]);
        $engine->adjustPoints($account, 200, 'Seed points', $admin);

        $reward = LoyaltyReward::query()->where('code', 'REWARD_5PCT')->firstOrFail();

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/v1/loyalty/redeem', [
            'reward_id' => $reward->id,
        ])->assertOk();

        $code = $response->json('data.promotion_code');
        $this->assertNotEmpty($code);
        $this->assertDatabaseHas('promotions', ['code' => $code]);
        $this->assertDatabaseHas('loyalty_redemptions', [
            'loyalty_account_id' => $account->id,
            'loyalty_reward_id' => $reward->id,
            'promotion_code' => $code,
        ]);
        $this->assertDatabaseHas('loyalty_ledger_entries', [
            'loyalty_account_id' => $account->id,
            'entry_type' => LoyaltyLedgerType::Redeem->value,
        ]);
    }

    public function test_tier_calculation_and_reward_activation(): void
    {
        $user = $this->customerUser();
        $engine = app(LoyaltyEngine::class);
        $account = $engine->ensureAccountForUser($user);
        $admin = Admin::factory()->create(['is_active' => true]);

        // Lifetime points only increase via earn ledger entries.
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'total' => 60000,
        ]);
        event(PaymentConfirmed::fromOrder($order));

        $profile = CustomerProfile::query()->where('user_id', $user->id)->firstOrFail();
        $profile->metrics()->updateOrCreate(
            ['customer_profile_id' => $profile->id],
            [
                'total_orders' => 5,
                'total_spend' => 150000,
                'currency' => 'TZS',
            ],
        );

        $updated = $engine->recalculateTier($account->fresh(['profile.metrics']));
        $this->assertSame('SILVER', $updated->tier?->code);

        Sanctum::actingAs($admin);
        $rewardId = LoyaltyReward::query()->where('code', 'REWARD_5PCT')->value('id');
        $this->putJson("/api/v1/admin/loyalty/rewards/{$rewardId}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_pos_loyalty_sale_and_crm_analytics_integration(): void
    {
        $super = Admin::factory()->superAdmin()->create();
        $store = $this->stores->create([
            'code' => 'LOY-01',
            'name' => 'Loyalty Store',
            'is_active' => true,
        ]);
        $cashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
            'is_active' => true,
        ]);
        $this->assignments->assign($cashier, $store, $super);

        $sku = $this->seedSku($store->id, 10000);
        $user = $this->customerUser();
        app(CustomerProfileService::class)->ensureForUser($user);

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $store->id,
            'terminal_id' => $store->terminals()->firstOrFail()->id,
            'opening_float' => 50000,
        ])->assertCreated();

        $lookup = $this->getJson('/api/v1/admin/pos/loyalty/lookup?customer_id='.$user->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $accountId = $lookup->json('data.id');
        $this->assertNotEmpty($accountId);

        $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [[
                'product_id' => $sku['product_id'],
                'product_variant_id' => $sku['variant_id'],
                'quantity' => 1,
            ]],
            'payment_method' => 'CASH',
            'amount_received' => 10000,
            'customer_id' => $user->id,
        ])->assertSuccessful();

        $account = LoyaltyAccount::query()->findOrFail($accountId);
        $this->assertGreaterThan(0, (int) $account->fresh()->points_balance);

        $this->getJson('/api/v1/admin/customers/'.$account->profile->id)
            ->assertOk()
            ->assertJsonPath('data.loyalty.loyalty_number', $account->loyalty_number);

        $this->getJson('/api/v1/admin/analytics/loyalty')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['summary' => ['active_loyalty_customers', 'points_issued']]]);
    }

    public function test_walk_in_sale_creates_no_loyalty_account(): void
    {
        $super = Admin::factory()->superAdmin()->create();
        $store = $this->stores->create([
            'code' => 'LOY-02',
            'name' => 'Walkin Store',
            'is_active' => true,
        ]);
        $cashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
            'is_active' => true,
        ]);
        $this->assignments->assign($cashier, $store, $super);
        $sku = $this->seedSku($store->id, 5000);

        $before = LoyaltyAccount::query()->count();

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $store->id,
            'terminal_id' => $store->terminals()->firstOrFail()->id,
            'opening_float' => 10000,
        ])->assertCreated();

        $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [[
                'product_id' => $sku['product_id'],
                'product_variant_id' => $sku['variant_id'],
                'quantity' => 1,
            ]],
            'payment_method' => 'CASH',
            'amount_received' => 5000,
        ])->assertSuccessful();

        $this->assertSame($before, LoyaltyAccount::query()->count());
    }

    public function test_admin_tier_and_rule_crud(): void
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/loyalty/tiers', [
            'code' => 'DIAMOND',
            'name' => 'Diamond',
            'sort_order' => 50,
            'min_lifetime_points' => 10000,
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.code', 'DIAMOND');

        $this->postJson('/api/v1/admin/loyalty/rules', [
            'code' => 'PROD_BONUS',
            'name' => 'Product bonus',
            'rule_type' => 'product',
            'points_awarded' => 5,
            'is_active' => true,
        ])->assertCreated();

        $this->assertTrue(LoyaltyTier::query()->where('code', 'DIAMOND')->exists());
    }

    private function customerUser(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::query()->where('slug', 'customer')->firstOrFail();
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    /**
     * @return array{product_id: string, variant_id: string}
     */
    private function seedSku(string $storeId, float $price): array
    {
        $product = Product::factory()->create([
            'store_id' => $storeId,
            'commerce_channel_id' => $this->tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'price' => $price,
            'cost_price' => round($price * 0.6, 2),
            'is_active' => true,
            'is_demo' => false,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Default',
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
        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'inventory_location_id' => $store->defaultInventoryLocation->id,
            'warehouse_code' => $store->defaultInventoryLocation->code,
            'on_hand' => 20,
            'reserved' => 0,
            'reorder_level' => 2,
            'is_active' => true,
        ]);

        return ['product_id' => $product->id, 'variant_id' => $variant->id];
    }
}

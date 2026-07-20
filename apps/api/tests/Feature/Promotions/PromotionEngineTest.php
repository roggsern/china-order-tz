<?php

namespace Tests\Feature\Promotions;

use App\Enums\ActivityEventType;
use App\Enums\CartStatus;
use App\Enums\PromotionDiscountType;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CustomerProfile;
use App\Models\CustomerTag;
use App\Models\OrderDiscountSnapshot;
use App\Models\Promotion;
use App\Models\PromotionUsage;
use App\Models\Role;
use App\Models\User;
use App\Services\Crm\CustomerProfileService;
use App\Services\Crm\CustomerSegmentationService;
use Database\Factories\Support\CatalogCartFixture;
use Database\Seeders\CustomerTagSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PromotionEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['promotions.reject_low_margin' => false]);
    }

    public function test_admin_promotion_crud_activate_and_authorization(): void
    {
        $this->postJson('/api/v1/admin/promotions', [
            'name' => 'Save 10',
            'code' => 'SAVE10',
            'type' => 'coupon',
            'discount_type' => 'percentage',
            'value' => 10,
        ])->assertUnauthorized();

        $admin = Admin::factory()->create(['is_active' => true]);
        Sanctum::actingAs($admin);

        $created = $this->postJson('/api/v1/admin/promotions', [
            'name' => 'Save 10',
            'code' => 'SAVE10',
            'type' => 'coupon',
            'discount_type' => 'percentage',
            'value' => 10,
            'status' => 'draft',
            'minimum_order_amount' => 1000,
        ])->assertCreated()
            ->assertJsonPath('data.code', 'SAVE10')
            ->assertJsonPath('data.status', 'draft');

        $id = $created->json('data.id');

        $this->patchJson("/api/v1/admin/promotions/{$id}/status", ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::PromotionCreated->value,
            'subject_id' => $id,
        ]);

        $this->getJson('/api/v1/admin/promotions')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->putJson("/api/v1/admin/promotions/{$id}", [
            'name' => 'Save 10 Updated',
            'value' => 15,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Save 10 Updated');
    }

    public function test_coupon_validation_apply_checkout_and_order_snapshot(): void
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        Sanctum::actingAs($admin);
        $promoId = $this->postJson('/api/v1/admin/promotions', [
            'name' => 'Welcome 10%',
            'code' => 'WELCOME10',
            'type' => 'coupon',
            'discount_type' => 'percentage',
            'value' => 10,
            'status' => 'active',
        ])->assertCreated()->json('data.id');

        $user = $this->customerUser();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(20000);
        $this->seedCart($user, $product->id, $variant->id, 1, 20000);

        Sanctum::actingAs($user);
        $sessionId = $this->postJson('/api/v1/checkout/start')->assertCreated()->json('data.id');

        $this->postJson('/api/v1/promotions/validate', [
            'code' => 'WELCOME10',
            'checkout_session_id' => $sessionId,
        ])->assertOk()
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.discount_total', '2000.00');

        $this->postJson('/api/v1/promotions/apply', [
            'code' => 'WELCOME10',
            'checkout_session_id' => $sessionId,
        ])->assertOk()
            ->assertJsonPath('data.discount_total', '2000.00')
            ->assertJsonPath('data.applied_promotion_code', 'WELCOME10')
            ->assertJsonPath('data.grand_total', '18000.00');

        $this->applyCheckoutShippingChoice($sessionId);

        $orderId = $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")
            ->assertCreated()
            ->assertJsonPath('data.discount_total', '2000.00')
            ->assertJsonPath('data.grand_total', '18000.00')
            ->json('data.id');

        $this->assertDatabaseHas('promotion_usages', [
            'promotion_id' => $promoId,
            'order_id' => $orderId,
            'customer_id' => $user->id,
        ]);

        $this->assertDatabaseHas('order_discount_snapshots', [
            'order_id' => $orderId,
            'promotion_id' => $promoId,
            'promotion_code' => 'WELCOME10',
        ]);

        $this->assertSame(1, OrderDiscountSnapshot::query()->where('order_id', $orderId)->count());
        $this->assertSame(1, PromotionUsage::query()->where('promotion_id', $promoId)->count());

        Sanctum::actingAs($admin);
        $this->getJson("/api/v1/admin/promotions/{$promoId}/usage")
            ->assertOk()
            ->assertJsonPath('data.0.order_id', $orderId);
    }

    public function test_automatic_promotion_applies_on_checkout_start(): void
    {
        Promotion::query()->create([
            'name' => 'Auto 5%',
            'code' => null,
            'type' => PromotionType::Automatic,
            'discount_type' => PromotionDiscountType::Percentage,
            'value' => 5,
            'status' => PromotionStatus::Active,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $user = $this->customerUser();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000);
        $this->seedCart($user, $product->id, $variant->id, 1, 10000);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/checkout/start')
            ->assertCreated()
            ->assertJsonPath('data.discount_total', '500.00')
            ->assertJsonPath('data.grand_total', '9500.00');
    }

    public function test_customer_tag_and_usage_limit_rules(): void
    {
        $this->seed(CustomerTagSeeder::class);
        $admin = Admin::factory()->create(['is_active' => true]);
        $user = $this->customerUser();
        $profile = app(CustomerProfileService::class)->ensureForUser($user);
        $vip = CustomerTag::query()->where('slug', 'vip')->firstOrFail();
        app(CustomerSegmentationService::class)->assignTag($profile, $vip, $admin);

        Sanctum::actingAs($admin);
        $promoId = $this->postJson('/api/v1/admin/promotions', [
            'name' => 'VIP Only',
            'code' => 'VIPONLY',
            'type' => 'coupon',
            'discount_type' => 'fixed_amount',
            'value' => 1000,
            'status' => 'active',
            'usage_limit' => 1,
            'rules' => [
                [
                    'rule_type' => 'customer_tag',
                    'rule_value' => ['slugs' => ['vip']],
                ],
            ],
        ])->assertCreated()->json('data.id');

        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(15000);
        $this->seedCart($user, $product->id, $variant->id, 1, 15000);
        Sanctum::actingAs($user);
        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');
        $this->postJson('/api/v1/promotions/apply', [
            'code' => 'VIPONLY',
            'checkout_session_id' => $sessionId,
        ])->assertOk()->assertJsonPath('data.discount_total', '1000.00');

        $this->applyCheckoutShippingChoice($sessionId);
        $orderId = $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")->assertCreated()->json('data.id');
        $this->assertDatabaseHas('promotion_usages', ['promotion_id' => $promoId, 'order_id' => $orderId]);

        // Second use blocked by usage_limit.
        $this->seedCart($user, $product->id, $variant->id, 1, 15000);
        $session2 = $this->postJson('/api/v1/checkout/start')->json('data.id');
        $this->postJson('/api/v1/promotions/apply', [
            'code' => 'VIPONLY',
            'checkout_session_id' => $session2,
        ])->assertStatus(422);
    }

    public function test_expiration_and_profit_protection(): void
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        Sanctum::actingAs($admin);

        $expiredId = $this->postJson('/api/v1/admin/promotions', [
            'name' => 'Expired',
            'code' => 'OLDCODE',
            'type' => 'coupon',
            'discount_type' => 'percentage',
            'value' => 50,
            'status' => 'active',
            'starts_at' => now()->subDays(10)->toIso8601String(),
            'ends_at' => now()->subDay()->toIso8601String(),
        ])->assertCreated()->json('data.id');

        // Ends in past but status active — eligibility fails on date window.
        $user = $this->customerUser();
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(10000);
        $product->update(['cost_price' => 9000]);
        $this->seedCart($user, $product->id, $variant->id, 1, 10000);
        Sanctum::actingAs($user);
        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');
        $this->postJson('/api/v1/promotions/apply', [
            'code' => 'OLDCODE',
            'checkout_session_id' => $sessionId,
        ])->assertStatus(422);

        // Profit protection: high cost + large discount.
        config(['promotions.reject_low_margin' => true, 'promotions.low_margin_threshold' => 20]);
        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/admin/promotions', [
            'name' => 'Deep Cut',
            'code' => 'DEEPCUT',
            'type' => 'coupon',
            'discount_type' => 'percentage',
            'value' => 50,
            'status' => 'active',
        ])->assertCreated();

        Sanctum::actingAs($user);
        $session2 = $this->postJson('/api/v1/checkout/start')->json('data.id');
        $this->postJson('/api/v1/promotions/apply', [
            'code' => 'DEEPCUT',
            'checkout_session_id' => $session2,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['promotion']);

        unset($expiredId);
    }

    public function test_guest_and_non_admin_rejected(): void
    {
        $this->postJson('/api/v1/promotions/validate', ['code' => 'X'])->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/promotions')->assertUnauthorized();
    }

    private function customerUser(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::query()->where('slug', 'customer')->firstOrFail();
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function seedCart(
        User $user,
        string $productId,
        string $variantId,
        int $quantity,
        float $unitPrice,
    ): void {
        Cart::query()->where('user_id', $user->id)->where('status', CartStatus::Active)->delete();

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
            'currency' => 'TZS',
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'price_snapshot' => $unitPrice,
            'currency' => 'TZS',
        ]);
    }
}

<?php

namespace Tests\Feature\Crm;

use App\Enums\ActivityEventType;
use App\Enums\CartStatus;
use App\Enums\CustomerRegistrationSource;
use App\Enums\CustomerTimelineEventType;
use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\CustomerProfile;
use App\Models\CustomerTag;
use App\Models\CustomerTimelineEvent;
use App\Models\Order;
use App\Models\ProfitRecord;
use App\Models\Role;
use App\Models\User;
use App\Services\Crm\CustomerCodeGenerator;
use App\Services\Crm\CustomerMetricsService;
use App\Services\Crm\CustomerProfileService;
use Database\Factories\Support\CatalogCartFixture;
use Database\Seeders\CustomerTagSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerCrmPlatformTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_registered_customer_appears_in_crm_and_admin_staff_do_not(): void
    {
        $this->seed(CustomerTagSeeder::class);

        $register = $this->postJson('/api/v1/register', [
            'name' => 'Asha Mwangi',
            'email' => 'asha.crm@example.com',
            'phone' => '+255712000111',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $userId = $register->json('data.id') ?? User::query()->where('email', 'asha.crm@example.com')->value('id');
        $this->assertNotNull($userId);

        $this->assertDatabaseHas('customer_profiles', [
            'user_id' => $userId,
            'registration_source' => CustomerRegistrationSource::SelfRegistration->value,
        ]);

        $admin = Admin::factory()->create(['is_active' => true]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/customers')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['email' => 'asha.crm@example.com']);

        // Admin model accounts never appear as CRM customers.
        $this->assertDatabaseMissing('customer_profiles', [
            'user_id' => $admin->id,
        ]);

        $codes = collect($this->getJson('/api/v1/admin/customers')->json('data'))
            ->pluck('email')
            ->all();
        $this->assertNotContains($admin->email, $codes);
    }

    public function test_checkout_registration_source_and_guest_cart_are_not_customers(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Checkout User',
            'email' => 'checkout.crm@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'registration_source' => CustomerRegistrationSource::CheckoutRegistration->value,
        ])->assertCreated();

        $userId = User::query()->where('email', 'checkout.crm@example.com')->value('id');
        $this->assertDatabaseHas('customer_profiles', [
            'user_id' => $userId,
            'registration_source' => CustomerRegistrationSource::CheckoutRegistration->value,
        ]);

        // Guest cart without a user must not create a CRM profile.
        Cart::factory()->create([
            'user_id' => null,
            'status' => CartStatus::Active,
        ]);
        $this->assertSame(
            1,
            CustomerProfile::query()->count(),
            'Only the registered checkout user should have a CRM profile.',
        );

        unset($response);
    }

    public function test_profile_creation_and_backfill_are_idempotent(): void
    {
        $user = User::factory()->create();
        $role = Role::query()->where('slug', 'customer')->firstOrFail();
        $user->roles()->syncWithoutDetaching([$role->id]);

        $service = app(CustomerProfileService::class);
        $a = $service->ensureForUser($user);
        $b = $service->ensureForUser($user);
        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, CustomerProfile::query()->where('user_id', $user->id)->count());

        $first = $service->backfillExistingCustomers();
        $second = $service->backfillExistingCustomers();
        $this->assertSame(0, $second['profiles_created']);
        $this->assertGreaterThanOrEqual($first['metrics_recalculated'], 1);
    }

    public function test_customer_code_generation_is_unique_under_locked_sequence(): void
    {
        $generator = app(CustomerCodeGenerator::class);
        $codes = [];
        for ($i = 0; $i < 5; $i++) {
            $codes[] = $generator->generate();
        }
        $this->assertCount(5, array_unique($codes));
        $this->assertSame('CTZ-CUS-000001', $codes[0]);
        $this->assertSame('CTZ-CUS-000005', $codes[4]);
        $this->assertSame(5, (int) DB::table('customer_code_sequences')->where('id', 1)->value('last_value'));
    }

    public function test_list_search_filters_sort_and_authorization(): void
    {
        $this->seed(CustomerTagSeeder::class);
        $admin = Admin::factory()->create(['is_active' => true]);

        $user = $this->makeCustomerUser('Filter Me', 'filter.me@example.com', '+255700111222');
        $profile = app(CustomerProfileService::class)->ensureForUser($user);
        app(CustomerMetricsService::class)->recalculate($profile);

        $this->getJson('/api/v1/admin/customers')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/customers')->assertUnauthorized();

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/customers?search=filter.me@example.com')
            ->assertOk()
            ->assertJsonPath('data.0.customer_code', $profile->customer_code);

        $this->getJson('/api/v1/admin/customers?lifecycle_status=active&sort=spend')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/v1/admin/customers/summary')
            ->assertOk()
            ->assertJsonPath('data.total_customers', 1);
    }

    public function test_metrics_update_after_order_and_profit_record(): void
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $user = $this->makeCustomerUser('Buyer', 'buyer.crm@example.com');
        $profile = app(CustomerProfileService::class)->ensureForUser($user);

        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(15000);
        $this->seedCart($user, $product->id, $variant->id, 1, 15000);

        Sanctum::actingAs($user);
        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');
        $this->applyCheckoutShippingChoice($sessionId);
        $orderId = $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")
            ->assertCreated()
            ->json('data.id');

        $profile->refresh();
        $metrics = $profile->metrics()->first();
        $this->assertNotNull($metrics);
        $this->assertGreaterThanOrEqual(1, (int) $metrics->total_orders);

        $order = Order::query()->findOrFail($orderId);
        $order->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);

        ProfitRecord::query()->create([
            'order_id' => $order->id,
            'revenue' => $order->total,
            'total_cost' => 5000,
            'gross_profit' => (float) $order->total - 5000,
            'margin_percentage' => 50,
            'currency' => 'TZS',
            'calculated_at' => now(),
        ]);

        event(new \App\Events\CostProfit\ProfitCalculated(
            ProfitRecord::query()->where('order_id', $order->id)->firstOrFail(),
        ));

        $metrics = app(CustomerMetricsService::class)->recalculate($profile->fresh());
        $this->assertSame('15000.00', (string) $metrics->total_spend);
        $this->assertEqualsWithDelta(10000.0, (float) $metrics->gross_profit_generated, 0.01);

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/customers/'.$profile->id)
            ->assertOk()
            ->assertJsonPath('data.metrics.total_orders', 1)
            ->assertJsonPath('data.customer_code', $profile->customer_code);
    }

    public function test_tags_notes_block_timeline_and_audit(): void
    {
        $this->seed(CustomerTagSeeder::class);
        $admin = Admin::factory()->create(['is_active' => true, 'name' => 'CRM Admin']);
        $user = $this->makeCustomerUser('Tagged', 'tagged.crm@example.com');
        $profile = app(CustomerProfileService::class)->ensureForUser($user);
        $tag = CustomerTag::query()->where('slug', 'vip')->firstOrFail();

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/customers/{$profile->id}/tags", [
            'tag_id' => $tag->id,
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('customer_profile_tag', [
            'customer_profile_id' => $profile->id,
            'customer_tag_id' => $tag->id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CustomerTagAssigned->value,
            'subject_id' => $profile->id,
        ]);

        $noteId = $this->postJson("/api/v1/admin/customers/{$profile->id}/notes", [
            'body' => 'Internal follow-up required',
            'is_pinned' => true,
        ])->assertCreated()
            ->json('data.id');

        // Notes must not appear on customer-facing profile APIs.
        Sanctum::actingAs($user);
        $profileResponse = $this->getJson('/api/v1/profile')->assertOk();
        $body = json_encode($profileResponse->json());
        $this->assertStringNotContainsString('Internal follow-up required', $body ?: '');
        $this->assertStringNotContainsString('customer_notes', $body ?: '');

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/v1/admin/customers/{$profile->id}/tags/{$tag->id}")
            ->assertOk();

        $this->patchJson("/api/v1/admin/customers/{$profile->id}/status", [
            'lifecycle_status' => 'blocked',
            'block_reason' => 'Fraud review',
        ])->assertOk()
            ->assertJsonPath('data.lifecycle_status', 'blocked');

        $user->refresh();
        $this->assertFalse((bool) $user->is_active);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/cart')->assertForbidden();

        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/admin/customers/{$profile->id}/status", [
            'lifecycle_status' => 'active',
        ])->assertOk()
            ->assertJsonPath('data.lifecycle_status', 'active');

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::CustomerBlocked->value,
        ]);

        $timelineTypes = CustomerTimelineEvent::query()
            ->where('customer_profile_id', $profile->id)
            ->pluck('event_type')
            ->map(fn ($t) => $t instanceof \BackedEnum ? $t->value : $t)
            ->all();

        $this->assertContains(CustomerTimelineEventType::TagAssigned->value, $timelineTypes);
        $this->assertContains(CustomerTimelineEventType::NoteAdded->value, $timelineTypes);
        $this->assertContains(CustomerTimelineEventType::CustomerBlocked->value, $timelineTypes);
        $this->assertGreaterThanOrEqual(3, count($timelineTypes));

        $this->deleteJson("/api/v1/admin/customers/{$profile->id}/notes/{$noteId}")
            ->assertOk();
        $this->assertDatabaseMissing('customer_notes', ['id' => $noteId]);
    }

    public function test_detail_related_collections_are_paginated(): void
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $user = $this->makeCustomerUser('Rel', 'rel.crm@example.com');
        $profile = app(CustomerProfileService::class)->ensureForUser($user);

        Sanctum::actingAs($admin);
        $this->getJson("/api/v1/admin/customers/{$profile->id}/orders")
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->getJson("/api/v1/admin/customers/{$profile->id}/payments")->assertOk();
        $this->getJson("/api/v1/admin/customers/{$profile->id}/shipments")->assertOk();
        $this->getJson("/api/v1/admin/customers/{$profile->id}/returns")->assertOk();
        $this->getJson("/api/v1/admin/customers/{$profile->id}/addresses")->assertOk();
        $this->getJson("/api/v1/admin/customers/{$profile->id}/timeline")->assertOk();
    }

    private function makeCustomerUser(string $name, string $email, ?string $phone = null): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'is_active' => true,
        ]);
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
        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
            'currency' => 'TZS',
        ]);

        \App\Models\CartItem::factory()->create([
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

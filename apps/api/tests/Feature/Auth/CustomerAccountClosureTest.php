<?php

namespace Tests\Feature\Auth;

use App\Enums\ActivityEventType;
use App\Enums\CartStatus;
use App\Enums\CustomerLifecycleStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Models\ActivityLog;
use App\Models\Cart;
use App\Models\CustomerProfile;
use App\Models\CustomerTimelineEvent;
use App\Models\DeliveryAddress;
use App\Models\DevicePushToken;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShippingAddress;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\Wishlist;
use App\Services\Crm\CustomerProfileService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerAccountClosureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function makeCustomer(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'email' => 'close.me@example.com',
            'password' => 'password123',
            'is_active' => true,
            'phone' => '+255712345678',
            'first_name' => 'Close',
            'last_name' => 'Me',
            'name' => 'Close Me',
        ], $overrides));

        $role = Role::query()->where('slug', 'customer')->firstOrFail();
        $user->roles()->syncWithoutDetaching([$role->id]);
        app(CustomerProfileService::class)->ensureForUser($user);

        return $user->fresh(['customerProfile']) ?? $user;
    }

    public function test_unauthenticated_cannot_close_account(): void
    {
        $this->postJson('/api/v1/account/close', [
            'current_password' => 'password123',
            'acknowledge' => true,
        ])->assertUnauthorized();
    }

    public function test_wrong_password_is_rejected_and_preserves_account(): void
    {
        $user = $this->makeCustomer();
        $token = $user->createToken('customer-api')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/account/close', [
                'current_password' => 'wrong-password',
                'acknowledge' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $user->refresh();
        $this->assertTrue($user->is_active);
        $this->assertNull($user->deleted_at);
        $this->assertSame('close.me@example.com', $user->email);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_authenticated_customer_closes_own_account(): void
    {
        NotificationFacade::fake();

        $user = $this->makeCustomer();
        $originalEmail = $user->email;
        $address = UserAddress::factory()->create(['user_id' => $user->id]);
        $cart = Cart::factory()->create(['user_id' => $user->id, 'status' => CartStatus::Active]);
        $wishlist = Wishlist::factory()->create(['user_id' => $user->id]);
        $order = Order::factory()->create(['user_id' => $user->id]);
        $payment = Payment::factory()->create(['user_id' => $user->id, 'order_id' => $order->id]);
        $refund = Refund::query()->create([
            'payment_id' => $payment->id,
            'order_id' => $order->id,
            'user_id' => $user->id,
            'amount' => 1000,
            'currency' => 'TZS',
            'status' => 'pending',
            'reason' => 'test',
        ]);
        $shipment = Shipment::factory()->create(['order_id' => $order->id]);
        DevicePushToken::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'notification_type' => NotificationEventType::OrderCreated->value,
            'channel' => NotificationChannel::Email->value,
            'is_enabled' => true,
        ]);
        Notification::factory()->create(['user_id' => $user->id, 'customer_id' => $user->id]);

        $currentToken = $user->createToken('customer-api-current')->plainTextToken;
        $otherToken = $user->createToken('customer-api-other')->plainTextToken;

        $this->withToken($currentToken)
            ->postJson('/api/v1/account/close', [
                'current_password' => 'password123',
                'acknowledge' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('requires_reauthentication', true)
            ->assertJsonPath('already_closed', false);

        $closed = User::withTrashed()->whereKey($user->id)->firstOrFail();
        $this->assertNotNull($closed->deleted_at);
        $this->assertFalse((bool) $closed->is_active);
        $this->assertSame(
            'deleted+'.strtolower(str_replace('-', '', $closed->id)).'@invalid.local',
            $closed->email,
        );
        $this->assertNotSame($originalEmail, $closed->email);
        $this->assertSame('Deleted Customer', $closed->name);
        $this->assertNull($closed->phone);
        $this->assertFalse(Hash::check('password123', $closed->password));
        $this->assertSame(0, $closed->tokens()->count());
        $this->assertSame(0, DevicePushToken::query()->where('user_id', $closed->id)->where('is_active', true)->count());
        $this->assertSoftDeleted('user_addresses', ['id' => $address->id]);
        $this->assertSoftDeleted('carts', ['id' => $cart->id]);
        $this->assertSoftDeleted('wishlists', ['id' => $wishlist->id]);
        $this->assertDatabaseMissing('notification_preferences', ['user_id' => $closed->id]);
        $this->assertSoftDeleted('notifications', ['user_id' => $closed->id]);

        $profile = CustomerProfile::query()->where('user_id', $closed->id)->firstOrFail();
        $this->assertSame(CustomerLifecycleStatus::Closed, $profile->lifecycle_status);
        $this->assertFalse((bool) $profile->marketing_opt_in);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'user_id' => $closed->id]);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'user_id' => $closed->id]);
        $this->assertDatabaseHas('refunds', ['id' => $refund->id, 'user_id' => $closed->id]);
        $this->assertDatabaseHas('shipments', ['id' => $shipment->id, 'order_id' => $order->id]);

        $this->assertTrue(
            ActivityLog::query()
                ->where('event_type', ActivityEventType::CustomerAccountClosed->value)
                ->where('subject_id', $closed->id)
                ->exists(),
        );
        $this->assertTrue(
            CustomerTimelineEvent::query()
                ->where('customer_profile_id', $profile->id)
                ->where('event_type', 'account_closed')
                ->exists(),
        );

        Auth::forgetGuards();
        $this->withToken($currentToken)->getJson('/api/v1/me')->assertUnauthorized();
        $this->withToken($otherToken)->getJson('/api/v1/me')->assertUnauthorized();

        $this->postJson('/api/v1/login', [
            'email' => $originalEmail,
            'password' => 'password123',
        ])->assertUnprocessable();

        $this->postJson('/api/v1/login', [
            'email' => $closed->email,
            'password' => 'password123',
        ])->assertUnprocessable();

        NotificationFacade::assertNothingSent();
    }

    public function test_customer_cannot_target_another_account(): void
    {
        $victim = $this->makeCustomer(['email' => 'victim@example.com', 'phone' => '+255700000001']);
        $attacker = $this->makeCustomer(['email' => 'attacker@example.com', 'phone' => '+255700000002']);
        Sanctum::actingAs($attacker);

        $this->postJson('/api/v1/account/close', [
            'current_password' => 'password123',
            'acknowledge' => true,
            'user_id' => $victim->id,
        ])->assertOk();

        $victim->refresh();
        $this->assertNull($victim->deleted_at);
        $this->assertTrue($victim->is_active);
        $this->assertSame('victim@example.com', $victim->email);

        $attackerClosed = User::withTrashed()->whereKey($attacker->id)->firstOrFail();
        $this->assertNotNull($attackerClosed->deleted_at);
    }

    public function test_repeat_close_after_token_revocation_is_unauthorized(): void
    {
        $user = $this->makeCustomer(['email' => 'repeat@example.com', 'phone' => '+255700000003']);
        $token = $user->createToken('customer-api')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/account/close', [
                'current_password' => 'password123',
                'acknowledge' => true,
            ])
            ->assertOk();

        Auth::forgetGuards();

        $this->withToken($token)
            ->postJson('/api/v1/account/close', [
                'current_password' => 'password123',
                'acknowledge' => true,
            ])
            ->assertUnauthorized();
    }

    public function test_original_email_can_register_again_after_closure(): void
    {
        $user = $this->makeCustomer(['email' => 'reuse@example.com', 'phone' => '+255700000004']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/account/close', [
            'current_password' => 'password123',
            'acknowledge' => true,
        ])->assertOk();

        Auth::forgetGuards();

        $this->postJson('/api/v1/register', [
            'name' => 'New Customer',
            'email' => 'reuse@example.com',
            'phone' => '+255700000099',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();
    }

    public function test_acknowledge_required(): void
    {
        $user = $this->makeCustomer(['email' => 'ack@example.com', 'phone' => '+255700000005']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/account/close', [
            'current_password' => 'password123',
            'acknowledge' => false,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['acknowledge']);
    }

    public function test_closure_anonymizes_required_delivery_address_fields_without_null_violations(): void
    {
        $user = $this->makeCustomer([
            'email' => 'delivery.close@example.com',
            'phone' => '+255700000006',
        ]);
        $originalEmail = $user->email;

        $delivery = DeliveryAddress::factory()->create([
            'user_id' => $user->id,
            'recipient_name' => 'Zion Mode',
            'phone' => '+255712345678',
            'country' => 'Tanzania',
            'region' => 'Dar es Salaam',
            'city' => 'Dar es Salaam',
            'district' => 'Kinondoni',
            'street' => '123 Samora Avenue',
            'landmark' => 'Near the market',
            'postal_code' => '14110',
        ]);

        $addressBook = UserAddress::factory()->create([
            'user_id' => $user->id,
            'recipient_name' => 'Zion Mode',
            'phone' => '+255712345678',
            'region' => 'Dar es Salaam',
            'address_line_2' => 'Block B',
        ]);

        $orderLinkedShipping = ShippingAddress::factory()->create([
            'user_id' => $user->id,
            'order_id' => Order::factory()->create(['user_id' => $user->id])->id,
            'first_name' => 'Zion',
            'last_name' => 'Mode',
            'phone' => '+255712345678',
            'address_line_1' => 'Order snapshot street',
            'city' => 'Dar es Salaam',
            'region' => 'Dar es Salaam',
        ]);

        $orderBookShipping = ShippingAddress::factory()->create([
            'user_id' => $user->id,
            'order_id' => null,
            'first_name' => 'Zion',
            'last_name' => 'Mode',
            'phone' => '+255712345678',
            'email' => 'zion@example.com',
            'address_line_1' => 'Saved shipping street',
            'city' => 'Arusha',
            'region' => 'Arusha',
        ]);

        $order = Order::factory()->create(['user_id' => $user->id]);
        $payment = Payment::factory()->create(['user_id' => $user->id, 'order_id' => $order->id]);
        DevicePushToken::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $token = $user->createToken('customer-api')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/account/close', [
                'current_password' => 'password123',
                'acknowledge' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $closed = User::withTrashed()->whereKey($user->id)->firstOrFail();
        $this->assertNotNull($closed->deleted_at);
        $this->assertFalse((bool) $closed->is_active);
        $this->assertSame(
            'deleted+'.strtolower(str_replace('-', '', $closed->id)).'@invalid.local',
            $closed->email,
        );
        $this->assertNotSame($originalEmail, $closed->email);
        $this->assertSame(0, $closed->tokens()->count());
        $this->assertSame(
            0,
            DevicePushToken::query()->where('user_id', $closed->id)->where('is_active', true)->count(),
        );

        // Delivery addresses have no SoftDeletes — hard-deleted after anonymize.
        $this->assertDatabaseMissing('delivery_addresses', ['id' => $delivery->id]);
        $this->assertSoftDeleted('user_addresses', ['id' => $addressBook->id]);
        $this->assertSoftDeleted('shipping_addresses', ['id' => $orderBookShipping->id]);

        // Order-linked shipping snapshots must remain intact.
        $this->assertDatabaseHas('shipping_addresses', [
            'id' => $orderLinkedShipping->id,
            'first_name' => 'Zion',
            'last_name' => 'Mode',
            'address_line_1' => 'Order snapshot street',
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'user_id' => $closed->id]);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'user_id' => $closed->id]);
    }

    public function test_wrong_password_rolls_back_and_leaves_delivery_address_intact(): void
    {
        $user = $this->makeCustomer([
            'email' => 'rollback.delivery@example.com',
            'phone' => '+255700000007',
        ]);

        $delivery = DeliveryAddress::factory()->create([
            'user_id' => $user->id,
            'recipient_name' => 'Keep Me',
            'region' => 'Mwanza',
            'district' => 'Nyamagana',
            'street' => 'Keep Street',
        ]);

        $token = $user->createToken('customer-api')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/account/close', [
                'current_password' => 'wrong-password',
                'acknowledge' => true,
            ])
            ->assertUnprocessable();

        $user->refresh();
        $this->assertTrue($user->is_active);
        $this->assertNull($user->deleted_at);
        $this->assertSame('rollback.delivery@example.com', $user->email);
        $this->assertDatabaseHas('delivery_addresses', [
            'id' => $delivery->id,
            'recipient_name' => 'Keep Me',
            'region' => 'Mwanza',
            'district' => 'Nyamagana',
            'street' => 'Keep Street',
        ]);
        $this->assertSame(1, $user->tokens()->count());
    }
}

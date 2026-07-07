<?php

namespace Tests\Feature\Profile;

use App\Models\Admin;
use App\Models\DeliveryAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerProfileAndAddressTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function deliveryAddressPayload(array $overrides = []): array
    {
        return array_merge([
            'recipient_name' => 'Jane Customer',
            'phone' => '0712345678',
            'country' => 'Tanzania',
            'region' => 'Dar es Salaam',
            'city' => 'Dar es Salaam',
            'district' => 'Kinondoni',
            'street' => 'Sam Nujoma Road',
            'landmark' => 'Near mall',
            'postal_code' => '14111',
        ], $overrides);
    }

    public function test_customer_views_profile(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Customer',
            'email' => 'jane@example.com',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.first_name', 'Jane')
            ->assertJsonPath('data.last_name', 'Customer')
            ->assertJsonPath('data.email', 'jane@example.com');
    }

    public function test_customer_updates_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/profile', [
            'first_name' => 'Janet',
            'last_name' => 'Mbuya',
            'phone' => '0798765432',
            'email' => 'janet@example.com',
        ])->assertOk()
            ->assertJsonPath('data.first_name', 'Janet')
            ->assertJsonPath('data.last_name', 'Mbuya')
            ->assertJsonPath('data.email', 'janet@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'Janet',
            'last_name' => 'Mbuya',
            'name' => 'Janet Mbuya',
            'email' => 'janet@example.com',
        ]);
    }

    public function test_customer_views_delivery_address(): void
    {
        $user = User::factory()->create();

        DeliveryAddress::factory()->create([
            'user_id' => $user->id,
            'street' => 'Sam Nujoma Road',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/profile/address')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.street', 'Sam Nujoma Road');
    }

    public function test_customer_updates_delivery_address(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/profile/address', $this->deliveryAddressPayload())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.recipient_name', 'Jane Customer')
            ->assertJsonPath('data.district', 'Kinondoni');

        $this->assertDatabaseHas('delivery_addresses', [
            'user_id' => $user->id,
            'street' => 'Sam Nujoma Road',
        ]);

        $this->assertSame(1, DeliveryAddress::query()->where('user_id', $user->id)->count());

        $this->patchJson('/api/v1/profile/address', $this->deliveryAddressPayload([
            'street' => 'Updated Street',
        ]))->assertOk()
            ->assertJsonPath('data.street', 'Updated Street');

        $this->assertSame(1, DeliveryAddress::query()->where('user_id', $user->id)->count());
    }

    public function test_admin_rejected(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $this->getJson('/api/v1/profile')->assertUnauthorized();
        $this->getJson('/api/v1/profile/address')->assertUnauthorized();
    }

    public function test_guest_rejected(): void
    {
        $this->getJson('/api/v1/profile')->assertUnauthorized();
        $this->getJson('/api/v1/profile/address')->assertUnauthorized();
    }
}

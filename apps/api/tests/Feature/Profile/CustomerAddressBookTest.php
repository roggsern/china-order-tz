<?php

namespace Tests\Feature\Profile;

use App\Models\DeliveryAddress;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerAddressBookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function addressPayload(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Home',
            'recipient_name' => 'Jane Customer',
            'phone' => '+255712345678',
            'street' => 'Sam Nujoma Road',
            'district' => 'Kinondoni',
            'city' => 'Dar es Salaam',
            'region' => 'Dar es Salaam',
            'country' => 'Tanzania',
            'postal_code' => '14111',
            'is_default' => false,
        ], $overrides);
    }

    public function test_customer_lists_own_addresses(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $mine = UserAddress::factory()->default()->create([
            'user_id' => $user->id,
            'address_line_1' => 'Mine Street',
        ]);
        UserAddress::factory()->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/account/addresses')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.street', 'Mine Street')
            ->assertJsonPath('meta.default_id', $mine->id);
    }

    public function test_customer_creates_address_and_first_becomes_default(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/account/addresses', $this->addressPayload())
            ->assertCreated()
            ->assertJsonPath('data.recipient_name', 'Jane Customer')
            ->assertJsonPath('data.street', 'Sam Nujoma Road')
            ->assertJsonPath('data.district', 'Kinondoni')
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $user->id,
            'address_line_1' => 'Sam Nujoma Road',
            'address_line_2' => 'Kinondoni',
            'is_default' => true,
        ]);
    }

    public function test_customer_updates_own_address(): void
    {
        $user = User::factory()->create();
        $address = UserAddress::factory()->default()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/account/addresses/{$address->id}", [
            'street' => 'Updated Street',
            'city' => 'Arusha',
            'region' => 'Arusha',
            'district' => 'Central',
            'recipient_name' => 'Jane Updated',
            'phone' => '+255712345678',
        ])->assertOk()
            ->assertJsonPath('data.street', 'Updated Street')
            ->assertJsonPath('data.city', 'Arusha');

        $this->assertDatabaseHas('user_addresses', [
            'id' => $address->id,
            'address_line_1' => 'Updated Street',
            'city' => 'Arusha',
        ]);
    }

    public function test_customer_cannot_access_another_users_address(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreign = UserAddress::factory()->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/account/addresses/{$foreign->id}", $this->addressPayload([
            'street' => 'Hacked',
        ]))->assertNotFound();

        $this->deleteJson("/api/v1/account/addresses/{$foreign->id}")
            ->assertNotFound();

        $this->patchJson("/api/v1/account/addresses/{$foreign->id}/default")
            ->assertNotFound();
    }

    public function test_customer_sets_default_and_syncs_delivery_address(): void
    {
        $user = User::factory()->create();
        $first = UserAddress::factory()->default()->create([
            'user_id' => $user->id,
            'address_line_1' => 'First Street',
        ]);
        $second = UserAddress::factory()->create([
            'user_id' => $user->id,
            'address_line_1' => 'Second Street',
            'address_line_2' => 'Ilala',
            'recipient_name' => 'Second Person',
            'phone' => '+255798765432',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/account/addresses/{$second->id}/default")
            ->assertOk()
            ->assertJsonPath('data.id', $second->id)
            ->assertJsonPath('data.is_default', true);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);

        $this->assertDatabaseHas('delivery_addresses', [
            'user_id' => $user->id,
            'street' => 'Second Street',
            'district' => 'Ilala',
            'recipient_name' => 'Second Person',
        ]);
    }

    public function test_customer_deletes_address_and_promotes_next_default(): void
    {
        $user = User::factory()->create();
        $default = UserAddress::factory()->default()->create(['user_id' => $user->id]);
        $other = UserAddress::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/account/addresses/{$default->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('user_addresses', ['id' => $default->id]);
        $this->assertTrue($other->fresh()->is_default);
    }

    public function test_guest_cannot_manage_addresses(): void
    {
        $this->getJson('/api/v1/account/addresses')->assertUnauthorized();
        $this->postJson('/api/v1/account/addresses', $this->addressPayload())->assertUnauthorized();
    }

    public function test_checkout_preloads_delivery_address_from_default_saved_address(): void
    {
        $user = User::factory()->create();
        UserAddress::factory()->default()->create([
            'user_id' => $user->id,
            'address_line_1' => 'Book Street',
            'address_line_2' => 'Kinondoni',
            'recipient_name' => 'Book Owner',
            'phone' => '+255712345678',
            'city' => 'Dar es Salaam',
            'region' => 'Dar es Salaam',
            'country' => 'Tanzania',
        ]);

        Sanctum::actingAs($user);

        $this->assertNull(DeliveryAddress::query()->where('user_id', $user->id)->first());

        $this->getJson('/api/v1/profile/address')
            ->assertOk()
            ->assertJsonPath('data.street', 'Book Street')
            ->assertJsonPath('data.district', 'Kinondoni');

        $this->assertDatabaseHas('delivery_addresses', [
            'user_id' => $user->id,
            'street' => 'Book Street',
        ]);
    }

    public function test_validation_requires_core_address_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/account/addresses', [
            'recipient_name' => '',
            'phone' => 'not-a-phone',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['recipient_name', 'phone', 'street', 'district', 'city', 'region']);
    }
}

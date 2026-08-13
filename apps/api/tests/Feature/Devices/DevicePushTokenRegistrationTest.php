<?php

namespace Tests\Feature\Devices;

use App\Models\DevicePushToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DevicePushTokenRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'push_token' => 'ExponentPushToken['.Str::random(22).']',
            'provider' => 'expo',
            'platform' => 'android',
            'installation_id' => (string) Str::uuid(),
            'app_version' => '0.1.0',
            'device_name' => 'Pixel Test',
        ], $overrides);
    }

    public function test_unauthenticated_registration_is_rejected(): void
    {
        $this->postJson('/api/v1/devices/push-tokens', $this->registrationPayload())
            ->assertUnauthorized();
    }

    public function test_authenticated_customer_can_register_first_device(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = $this->registrationPayload();

        $response = $this->postJson('/api/v1/devices/push-tokens', $payload)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.provider', 'expo')
            ->assertJsonPath('data.platform', 'android')
            ->assertJsonPath('data.installation_id', strtolower($payload['installation_id']))
            ->assertJsonPath('data.is_active', true)
            ->assertJsonMissing(['push_token' => $payload['push_token']]);

        $this->assertDatabaseHas('device_push_tokens', [
            'user_id' => $user->id,
            'push_token' => $payload['push_token'],
            'installation_id' => strtolower($payload['installation_id']),
            'is_active' => true,
        ]);

        $this->assertNotNull($response->json('data.id'));
    }

    public function test_repeat_registration_is_idempotent(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $payload = $this->registrationPayload();

        $first = $this->postJson('/api/v1/devices/push-tokens', $payload)->assertCreated();
        $second = $this->postJson('/api/v1/devices/push-tokens', $payload)->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, DevicePushToken::query()->where('user_id', $user->id)->count());
    }

    public function test_same_customer_can_register_multiple_devices(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/devices/push-tokens', $this->registrationPayload())->assertCreated();
        $this->postJson('/api/v1/devices/push-tokens', $this->registrationPayload())->assertCreated();

        $this->assertSame(2, DevicePushToken::query()->where('user_id', $user->id)->where('is_active', true)->count());
    }

    public function test_token_rotation_updates_same_installation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $installationId = (string) Str::uuid();
        $firstToken = 'ExponentPushToken['.Str::random(22).']';
        $rotatedToken = 'ExponentPushToken['.Str::random(22).']';

        $first = $this->postJson('/api/v1/devices/push-tokens', $this->registrationPayload([
            'push_token' => $firstToken,
            'installation_id' => $installationId,
        ]))->assertCreated();

        $second = $this->postJson('/api/v1/devices/push-tokens', $this->registrationPayload([
            'push_token' => $rotatedToken,
            'installation_id' => $installationId,
        ]))->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, DevicePushToken::query()->count());
        $this->assertDatabaseHas('device_push_tokens', [
            'id' => $first->json('data.id'),
            'push_token' => $rotatedToken,
            'user_id' => $user->id,
            'is_active' => true,
        ]);
        $this->assertDatabaseMissing('device_push_tokens', [
            'push_token' => $firstToken,
        ]);
    }

    public function test_same_token_reassigned_from_customer_a_to_b(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $pushToken = 'ExponentPushToken['.Str::random(22).']';
        $installationId = (string) Str::uuid();

        Sanctum::actingAs($userA);
        $this->postJson('/api/v1/devices/push-tokens', $this->registrationPayload([
            'push_token' => $pushToken,
            'installation_id' => $installationId,
        ]))->assertCreated();

        Sanctum::actingAs($userB);
        $this->postJson('/api/v1/devices/push-tokens', $this->registrationPayload([
            'push_token' => $pushToken,
            'installation_id' => $installationId,
        ]))->assertCreated();

        $this->assertSame(1, DevicePushToken::query()->count());
        $row = DevicePushToken::query()->first();
        $this->assertNotNull($row);
        $this->assertSame($userB->id, $row->user_id);
        $this->assertTrue($row->is_active);
        $this->assertSame(0, DevicePushToken::query()->where('user_id', $userA->id)->where('is_active', true)->count());
    }

    public function test_previous_owner_loses_active_ownership_after_reassignment(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $pushToken = 'ExponentPushToken['.Str::random(22).']';

        Sanctum::actingAs($userA);
        $this->postJson('/api/v1/devices/push-tokens', $this->registrationPayload([
            'push_token' => $pushToken,
        ]))->assertCreated();

        Sanctum::actingAs($userB);
        $this->postJson('/api/v1/devices/push-tokens', $this->registrationPayload([
            'push_token' => $pushToken,
            'installation_id' => (string) Str::uuid(),
        ]))->assertCreated();

        $this->assertSame($userB->id, DevicePushToken::query()->where('push_token', $pushToken)->value('user_id'));
        $this->assertFalse(
            DevicePushToken::query()
                ->where('user_id', $userA->id)
                ->where('push_token', $pushToken)
                ->where('is_active', true)
                ->exists(),
        );
    }

    public function test_logout_with_installation_detaches_only_current_device(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('customer-api')->plainTextToken;
        $installA = (string) Str::uuid();
        $installB = (string) Str::uuid();

        $this->withToken($token)->postJson('/api/v1/devices/push-tokens', $this->registrationPayload([
            'installation_id' => $installA,
            'push_token' => 'ExponentPushToken['.Str::random(22).']',
        ]))->assertCreated();

        $this->withToken($token)->postJson('/api/v1/devices/push-tokens', $this->registrationPayload([
            'installation_id' => $installB,
            'push_token' => 'ExponentPushToken['.Str::random(22).']',
        ]))->assertCreated();

        $this->withToken($token)->postJson('/api/v1/logout', [
            'installation_id' => $installA,
        ])->assertOk();

        $this->assertFalse(
            DevicePushToken::query()
                ->where('installation_id', strtolower($installA))
                ->where('is_active', true)
                ->exists(),
        );
        $this->assertTrue(
            DevicePushToken::query()
                ->where('installation_id', strtolower($installB))
                ->where('is_active', true)
                ->exists(),
        );
    }

    public function test_logout_without_device_hints_keeps_push_tokens_for_web_compat(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('customer-api')->plainTextToken;
        $installationId = (string) Str::uuid();

        $this->withToken($token)->postJson('/api/v1/devices/push-tokens', $this->registrationPayload([
            'installation_id' => $installationId,
        ]))->assertCreated();

        $this->withToken($token)->postJson('/api/v1/logout', [])->assertOk();

        $this->assertTrue(
            DevicePushToken::query()
                ->where('user_id', $user->id)
                ->where('installation_id', strtolower($installationId))
                ->where('is_active', true)
                ->exists(),
            'Web logout without installation_id must not revoke all devices.',
        );
    }

    public function test_explicit_deactivate_endpoint(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $installationId = (string) Str::uuid();

        $this->postJson('/api/v1/devices/push-tokens', $this->registrationPayload([
            'installation_id' => $installationId,
        ]))->assertCreated();

        $this->deleteJson('/api/v1/devices/push-tokens', [
            'installation_id' => $installationId,
        ])
            ->assertOk()
            ->assertJsonPath('data.deactivated', 1);

        $this->assertFalse(
            DevicePushToken::query()
                ->where('installation_id', strtolower($installationId))
                ->where('is_active', true)
                ->exists(),
        );
    }

    public function test_invalid_provider_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/devices/push-tokens', $this->registrationPayload([
            'provider' => 'firebase',
        ]))->assertStatus(422)->assertJsonValidationErrors(['provider']);
    }

    public function test_invalid_platform_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/devices/push-tokens', $this->registrationPayload([
            'platform' => 'web',
        ]))->assertStatus(422)->assertJsonValidationErrors(['platform']);
    }

    public function test_oversized_token_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/devices/push-tokens', $this->registrationPayload([
            'push_token' => str_repeat('a', 513),
        ]))->assertStatus(422)->assertJsonValidationErrors(['push_token']);
    }

    public function test_short_token_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/devices/push-tokens', $this->registrationPayload([
            'push_token' => 'short-token',
        ]))->assertStatus(422)->assertJsonValidationErrors(['push_token']);
    }

    public function test_invalid_installation_id_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/devices/push-tokens', $this->registrationPayload([
            'installation_id' => 'not-a-uuid',
        ]))->assertStatus(422)->assertJsonValidationErrors(['installation_id']);
    }

    public function test_deactivating_user_revokes_active_push_tokens(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        DevicePushToken::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        DevicePushToken::factory()->create(['user_id' => $user->id, 'is_active' => true]);

        $user->forceFill(['is_active' => false])->save();

        $this->assertSame(0, DevicePushToken::query()->where('user_id', $user->id)->where('is_active', true)->count());
        $this->assertSame(2, DevicePushToken::query()->where('user_id', $user->id)->whereNotNull('revoked_at')->count());
    }

    public function test_soft_deleting_user_revokes_then_cascade_removes_rows_on_force_delete(): void
    {
        $user = User::factory()->create();
        DevicePushToken::factory()->create(['user_id' => $user->id]);

        $user->delete();

        $this->assertSame(0, DevicePushToken::query()->where('user_id', $user->id)->where('is_active', true)->count());

        $user->forceDelete();

        $this->assertSame(0, DevicePushToken::query()->where('user_id', $user->id)->count());
    }

    public function test_user_id_cannot_be_spoofed_from_request_body(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        Sanctum::actingAs($attacker);

        $payload = $this->registrationPayload();
        $payload['user_id'] = $owner->id;

        $this->postJson('/api/v1/devices/push-tokens', $payload)->assertCreated();

        $this->assertDatabaseHas('device_push_tokens', [
            'user_id' => $attacker->id,
            'push_token' => $payload['push_token'],
        ]);
        $this->assertDatabaseMissing('device_push_tokens', [
            'user_id' => $owner->id,
            'push_token' => $payload['push_token'],
        ]);
    }

    public function test_existing_active_row_app_reopen_refresh_succeeds_repeatedly(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $pushToken = 'ExponentPushToken['.Str::random(22).']';
        $installationId = (string) Str::uuid();

        DevicePushToken::factory()->create([
            'user_id' => $user->id,
            'push_token' => $pushToken,
            'provider' => 'expo',
            'platform' => 'android',
            'installation_id' => strtolower($installationId),
            'is_active' => true,
            'revoked_at' => null,
        ]);

        $payload = $this->registrationPayload([
            'push_token' => $pushToken,
            'installation_id' => $installationId,
        ]);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/devices/push-tokens', $payload)
                ->assertCreated()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.installation_id', strtolower($installationId));
        }

        $this->assertSame(1, DevicePushToken::query()->where('push_token', $pushToken)->count());
        $this->assertSame(1, DevicePushToken::query()->where('installation_id', strtolower($installationId))->count());
    }

    public function test_controller_does_not_depend_on_api_response_helper_class_name_in_body(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/devices/push-tokens', $this->registrationPayload())
            ->assertCreated();

        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'provider',
                'platform',
                'installation_id',
                'is_active',
            ],
        ]);
        $this->assertTrue($response->json('success'));
    }
}

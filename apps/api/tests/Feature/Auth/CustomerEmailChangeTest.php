<?php

namespace Tests\Feature\Auth;

use App\Enums\ActivityEventType;
use App\Enums\NotificationEventType;
use App\Models\ActivityLog;
use App\Models\EmailChangeRequest;
use App\Models\Notification;
use App\Models\User;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerEmailChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NotificationTemplateSeeder::class);
        config(['app.frontend_url' => 'http://localhost:3000']);
    }

    public function test_wrong_password_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'correct-password-123',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/account/email-change', [
            'new_email' => 'new@example.com',
            'current_password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this->assertDatabaseMissing('email_change_requests', [
            'user_id' => $user->id,
        ]);
        $this->assertSame('jane@example.com', $user->fresh()->email);
    }

    public function test_duplicate_email_rejected(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'correct-password-123',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/account/email-change', [
            'new_email' => 'taken@example.com',
            'current_password' => 'correct-password-123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['new_email']);
    }

    public function test_pending_created_without_changing_email(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'correct-password-123',
            'email_verified_at' => null,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/account/email-change', [
            'new_email' => 'new@example.com',
            'current_password' => 'correct-password-123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pending_email', 'new@example.com');

        $this->assertSame('jane@example.com', $user->fresh()->email);
        $this->assertDatabaseHas('email_change_requests', [
            'user_id' => $user->id,
            'old_email' => 'jane@example.com',
            'new_email' => 'new@example.com',
            'confirmed_at' => null,
        ]);

        $this->assertTrue(
            ActivityLog::query()
                ->where('event_type', ActivityEventType::CustomerEmailChangeRequested->value)
                ->where('actor_id', $user->id)
                ->exists(),
        );

        $this->assertTrue(
            Notification::query()
                ->where('user_id', $user->id)
                ->where('type', NotificationEventType::EmailChangeRequested->value)
                ->exists(),
        );
    }

    public function test_confirmation_updates_email_and_creates_audit(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'correct-password-123',
            'email_verified_at' => null,
        ]);

        $plainToken = 'email-change-token-'.str_repeat('a', 40);
        EmailChangeRequest::query()->create([
            'user_id' => $user->id,
            'old_email' => 'jane@example.com',
            'new_email' => 'new@example.com',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addHour(),
        ]);

        $this->postJson('/api/v1/account/email-change/confirm', [
            'token' => $plainToken,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'new@example.com');

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);

        $this->assertDatabaseHas('email_change_requests', [
            'user_id' => $user->id,
            'new_email' => 'new@example.com',
        ]);
        $this->assertNotNull(
            EmailChangeRequest::query()->where('user_id', $user->id)->value('confirmed_at'),
        );

        $this->assertTrue(
            ActivityLog::query()
                ->where('event_type', ActivityEventType::CustomerEmailChanged->value)
                ->where('subject_id', $user->id)
                ->exists(),
        );

        $this->assertTrue(
            Notification::query()
                ->where('user_id', $user->id)
                ->where('type', NotificationEventType::EmailChanged->value)
                ->exists(),
        );
    }

    public function test_expired_token_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
        ]);

        $plainToken = 'expired-token-'.str_repeat('b', 40);
        EmailChangeRequest::query()->create([
            'user_id' => $user->id,
            'old_email' => 'jane@example.com',
            'new_email' => 'new@example.com',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/v1/account/email-change/confirm', [
            'token' => $plainToken,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);

        $this->assertSame('jane@example.com', $user->fresh()->email);
    }

    public function test_profile_patch_does_not_mutate_email(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'first_name' => 'Jane',
            'last_name' => 'Customer',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/profile', [
            'first_name' => 'Janet',
            'last_name' => 'Mbuya',
            'phone' => '+255798765432',
            'email' => 'attacker@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Janet')
            ->assertJsonPath('data.email', 'jane@example.com');

        $this->assertSame('jane@example.com', $user->fresh()->email);
    }

    public function test_guest_cannot_request_email_change(): void
    {
        $this->postJson('/api/v1/account/email-change', [
            'new_email' => 'new@example.com',
            'current_password' => 'correct-password-123',
        ])->assertUnauthorized();
    }
}

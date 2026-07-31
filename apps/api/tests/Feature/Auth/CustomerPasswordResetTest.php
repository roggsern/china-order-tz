<?php

namespace Tests\Feature\Auth;

use App\Enums\ActivityEventType;
use App\Enums\NotificationEventType;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\User;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class CustomerPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NotificationTemplateSeeder::class);
        config(['app.frontend_url' => 'http://localhost:3000']);
    }

    public function test_forgot_password_success_for_known_email(): void
    {
        $user = User::factory()->create([
            'email' => 'resetme@example.com',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'resetme@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'message',
                'If an account exists for that email, password reset instructions have been sent.',
            );

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'resetme@example.com',
        ]);

        $this->assertTrue(
            Notification::query()
                ->where('user_id', $user->id)
                ->where('type', NotificationEventType::PasswordReset->value)
                ->exists(),
        );

        $this->assertTrue(
            ActivityLog::query()
                ->where('event_type', ActivityEventType::CustomerPasswordResetRequested->value)
                ->where('subject_id', $user->id)
                ->exists(),
        );
    }

    public function test_forgot_password_unknown_email_returns_safe_response(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'missing@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'message',
                'If an account exists for that email, password reset instructions have been sent.',
            );

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'missing@example.com',
        ]);
    }

    public function test_reset_password_success_updates_password_and_revokes_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'resetme@example.com',
            'password' => 'old-password-123',
            'is_active' => true,
        ]);

        $plainTextToken = $user->createToken('customer-api')->plainTextToken;
        $this->assertSame(1, $user->tokens()->count());

        $resetToken = Password::broker('users')->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'resetme@example.com',
            'token' => $resetToken,
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-456', $user->password));
        $this->assertFalse(Hash::check('old-password-123', $user->password));
        $this->assertSame(0, $user->tokens()->count());

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'resetme@example.com',
        ]);

        $this->assertTrue(
            ActivityLog::query()
                ->where('event_type', ActivityEventType::CustomerPasswordResetCompleted->value)
                ->where('subject_id', $user->id)
                ->exists(),
        );

        // Old bearer token should no longer authenticate.
        $this->withToken($plainTextToken)
            ->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    public function test_invalid_token_rejected(): void
    {
        User::factory()->create([
            'email' => 'resetme@example.com',
            'password' => 'old-password-123',
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'resetme@example.com',
            'token' => 'not-a-real-token',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_expired_token_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'resetme@example.com',
            'password' => 'old-password-123',
        ]);

        $resetToken = Password::broker('users')->createToken($user);

        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->update(['created_at' => now()->subHours(2)]);

        config(['auth.passwords.users.expire' => 60]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'resetme@example.com',
            'token' => $resetToken,
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_forgot_password_is_throttled(): void
    {
        User::factory()->create([
            'email' => 'throttle@example.com',
            'is_active' => true,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/forgot-password', [
                'email' => 'throttle@example.com',
            ])->assertOk();
        }

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'throttle@example.com',
        ])->assertStatus(429);
    }

    public function test_forgot_password_requires_valid_email(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'not-an-email',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_reset_password_requires_confirmation(): void
    {
        $user = User::factory()->create(['email' => 'resetme@example.com']);
        $token = Password::broker('users')->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'resetme@example.com',
            'token' => $token,
            'password' => 'new-password-456',
            'password_confirmation' => 'mismatch',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }
}

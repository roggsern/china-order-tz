<?php

namespace Tests\Feature\Auth;

use App\Enums\ActivityEventType;
use App\Enums\NotificationEventType;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\User;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(NotificationTemplateSeeder::class);
        config(['app.frontend_url' => 'http://localhost:3000']);
    }

    public function test_registration_sends_verification_and_leaves_email_unverified(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'Jane Customer',
            'email' => 'verify-me@example.com',
            'phone' => '+255712345678',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = User::query()->where('email', 'verify-me@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->hasVerifiedEmail());

        $this->assertTrue(
            Notification::query()
                ->where('user_id', $user->id)
                ->where('type', NotificationEventType::EmailVerificationRequested->value)
                ->exists(),
        );

        $this->assertTrue(
            ActivityLog::query()
                ->where('event_type', ActivityEventType::CustomerEmailVerificationRequested->value)
                ->where('subject_id', $user->id)
                ->exists(),
        );
    }

    public function test_valid_signed_token_verifies_email(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'verify-me@example.com',
        ]);

        $hash = sha1($user->getEmailForVerification());
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => $hash],
        );

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('already_verified', false);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasVerifiedEmail());

        $this->assertTrue(
            ActivityLog::query()
                ->where('event_type', ActivityEventType::CustomerEmailVerified->value)
                ->where('subject_id', $user->id)
                ->exists(),
        );

        $this->assertTrue(
            Notification::query()
                ->where('user_id', $user->id)
                ->where('type', NotificationEventType::EmailVerified->value)
                ->exists(),
        );
    }

    public function test_invalid_hash_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => 'deadbeef'],
        );

        $this->getJson($url)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hash']);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_already_verified_is_safe(): void
    {
        $user = User::factory()->create([
            'email' => 'already@example.com',
            'email_verified_at' => now(),
        ]);

        $hash = sha1($user->getEmailForVerification());
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => $hash],
        );

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('already_verified', true)
            ->assertJsonPath('message', 'Your email is already verified.');
    }

    public function test_resend_sends_for_unverified_user(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'resend@example.com',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/account/email/verify/resend')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('already_verified', false);

        $this->assertTrue(
            Notification::query()
                ->where('user_id', $user->id)
                ->where('type', NotificationEventType::EmailVerificationRequested->value)
                ->exists(),
        );
    }

    public function test_resend_is_throttled(): void
    {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/account/email/verify/resend')->assertOk();
        }

        $this->postJson('/api/v1/account/email/verify/resend')->assertStatus(429);
    }

    public function test_spa_confirm_endpoint_verifies_with_signed_fields(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'spa@example.com',
        ]);

        $hash = sha1($user->getEmailForVerification());
        $signed = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => $hash],
        );

        $parts = parse_url($signed);
        parse_str($parts['query'] ?? '', $query);

        $this->postJson('/api/v1/account/email/verify', [
            'id' => $user->id,
            'hash' => $hash,
            'expires' => $query['expires'],
            'signature' => $query['signature'],
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_guest_cannot_resend(): void
    {
        $this->postJson('/api/v1/account/email/verify/resend')->assertUnauthorized();
    }
}

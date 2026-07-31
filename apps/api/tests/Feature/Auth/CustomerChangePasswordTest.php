<?php

namespace Tests\Feature\Auth;

use App\Enums\ActivityEventType;
use App\Enums\NotificationEventType;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\User;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NotificationTemplateSeeder::class);
    }

    public function test_correct_current_password_succeeds_and_revokes_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'old-password-123',
            'is_active' => true,
        ]);

        $currentToken = $user->createToken('customer-api-current')->plainTextToken;
        $otherToken = $user->createToken('customer-api-other')->plainTextToken;
        $this->assertSame(2, $user->tokens()->count());

        $this->withToken($currentToken)
            ->postJson('/api/v1/account/change-password', [
                'current_password' => 'old-password-123',
                'password' => 'new-password-456',
                'password_confirmation' => 'new-password-456',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('requires_reauthentication', true)
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('data.password');

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-456', $user->password));
        $this->assertFalse(Hash::check('old-password-123', $user->password));
        $this->assertSame(0, $user->tokens()->count());

        $this->assertTrue(
            ActivityLog::query()
                ->where('event_type', ActivityEventType::CustomerPasswordChanged->value)
                ->where('subject_id', $user->id)
                ->exists(),
        );

        $this->assertTrue(
            Notification::query()
                ->where('user_id', $user->id)
                ->where('type', NotificationEventType::PasswordChanged->value)
                ->exists(),
        );

        Auth::forgetGuards();

        $this->withToken($currentToken)->getJson('/api/v1/me')->assertUnauthorized();
        $this->withToken($otherToken)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_wrong_current_password_rejected(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password-123',
        ]);
        $token = $user->createToken('customer-api')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/account/change-password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password-456',
                'password_confirmation' => 'new-password-456',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $user->refresh();
        $this->assertTrue(Hash::check('old-password-123', $user->password));
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_weak_password_rejected(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password-123',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/account/change-password', [
            'current_password' => 'old-password-123',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_guest_cannot_change_password(): void
    {
        $this->postJson('/api/v1/account/change-password', [
            'current_password' => 'old-password-123',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])->assertUnauthorized();
    }

    public function test_same_password_rejected(): void
    {
        $user = User::factory()->create([
            'password' => 'same-password-123',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/account/change-password', [
            'current_password' => 'same-password-123',
            'password' => 'same-password-123',
            'password_confirmation' => 'same-password-123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }
}

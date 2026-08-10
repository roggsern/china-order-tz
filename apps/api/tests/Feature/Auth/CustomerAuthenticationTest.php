<?php

namespace Tests\Feature\Auth;

use App\Enums\ActivityEventType;
use App\Jobs\Auth\SendCustomerEmailVerificationJob;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Role;
use App\Models\User;
use App\Services\Notifications\NotificationPlatform;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_customer_can_register(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Jane Customer',
            'email' => 'jane@example.com',
            'phone' => '+255712345678',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Registration successful')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('data.email', 'jane@example.com')
            ->assertJsonStructure(['token', 'token_type', 'data']);

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'name' => 'Jane Customer',
            'first_name' => 'Jane',
            'last_name' => 'Customer',
        ]);

        $user = User::query()->where('email', 'jane@example.com')->firstOrFail();
        $customerRole = Role::query()->where('slug', 'customer')->firstOrFail();

        $this->assertTrue($user->roles()->where('roles.id', $customerRole->id)->exists());
    }

    public function test_registration_persists_explicit_first_and_last_name(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Robert Musa',
            'first_name' => 'Robert',
            'last_name' => 'Musa',
            'email' => 'robert.musa@example.com',
            'phone' => '+255712345678',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'robert.musa@example.com',
            'name' => 'Robert Musa',
            'first_name' => 'Robert',
            'last_name' => 'Musa',
        ]);
    }

    public function test_registration_splits_display_name_when_first_last_omitted(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'Robert Musa',
            'email' => 'robert.split@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'robert.split@example.com',
            'name' => 'Robert Musa',
            'first_name' => 'Robert',
            'last_name' => 'Musa',
        ]);
    }

    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/v1/register', [
            'name' => 'Jane Customer',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_customer_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonStructure(['token', 'token_type', 'data']);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'login@example.com']);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'invalid_credentials')
            ->assertJsonPath('message', 'Invalid credentials')
            ->assertJsonPath('errors.email.0', 'Invalid credentials');
    }

    public function test_login_rejects_inactive_account(): void
    {
        User::factory()->inactive()->create([
            'email' => 'inactive@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'inactive@example.com',
            'password' => 'password123',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'account_disabled')
            ->assertJsonPath('message', 'Your account has been disabled.');
    }

    public function test_authenticated_customer_can_view_me(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_customer_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('customer-api')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/logout')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Logged out successfully')
            ->assertJsonPath('data', null);

        $this->assertSame(0, $user->fresh()->tokens()->count());

        $this->assertTrue(
            ActivityLog::query()
                ->where('event_type', ActivityEventType::CustomerLogout->value)
                ->where('subject_id', $user->id)
                ->exists(),
        );

        Auth::forgetGuards();

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_unauthenticated_logout_is_safe(): void
    {
        $this->postJson('/api/v1/logout')->assertUnauthorized();
    }

    public function test_customer_logout_revokes_only_current_token(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('customer-api-current')->plainTextToken;
        $otherDevice = $user->createToken('customer-api-other')->plainTextToken;

        $this->assertSame(2, $user->fresh()->tokens()->count());

        $this->withToken($current)->postJson('/api/v1/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(1, $user->fresh()->tokens()->count());

        Auth::forgetGuards();

        $this->withToken($current)->getJson('/api/v1/me')->assertUnauthorized();
        $this->withToken($otherDevice)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_customer_logout_does_not_affect_other_users(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $tokenA = $userA->createToken('customer-api')->plainTextToken;
        $tokenB = $userB->createToken('customer-api')->plainTextToken;

        $this->withToken($tokenA)->postJson('/api/v1/logout')->assertOk();

        $this->assertSame(0, $userA->fresh()->tokens()->count());
        $this->assertSame(1, $userB->fresh()->tokens()->count());

        Auth::forgetGuards();

        $this->withToken($tokenB)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $userB->id);
    }

    public function test_registration_queues_email_verification_and_still_returns_token(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/register', [
            'name' => 'Async Customer',
            'email' => 'async.customer@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Registration successful')
            ->assertJsonStructure(['token', 'token_type', 'data']);

        $this->assertDatabaseHas('users', [
            'email' => 'async.customer@example.com',
        ]);

        Queue::assertPushed(SendCustomerEmailVerificationJob::class, function (SendCustomerEmailVerificationJob $job) {
            $user = User::query()->where('email', 'async.customer@example.com')->first();

            return $user !== null && $job->userId === $user->id;
        });
    }

    public function test_registration_succeeds_when_email_verification_service_fails(): void
    {
        $this->mock(NotificationPlatform::class, function ($mock) {
            $mock->shouldReceive('notifyCustomer')
                ->once()
                ->andThrow(new \RuntimeException('SMTP unavailable'));
        });

        Log::spy();

        $response = $this->postJson('/api/v1/register', [
            'name' => 'Mail Fail Customer',
            'email' => 'mail.fail@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['token', 'token_type', 'data']);

        $this->assertDatabaseHas('users', [
            'email' => 'mail.fail@example.com',
        ]);

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context = []) {
                return $message === 'auth.email_verification_job.failed'
                    && isset($context['user_id']);
            })
            ->atLeast()
            ->once();
    }

    public function test_email_verification_job_logs_failure_without_throwing(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'job.fail@example.com',
        ]);

        $this->mock(NotificationPlatform::class, function ($mock) {
            $mock->shouldReceive('notifyCustomer')
                ->once()
                ->andThrow(new \RuntimeException('provider down'));
        });

        Log::spy();

        (new SendCustomerEmailVerificationJob($user->id))->handle();

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message) {
                return $message === 'auth.email_verification_job.failed';
            })
            ->once();
    }

    public function test_unauthenticated_me_returns_401(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_admin_token_rejected_on_customer_me(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_customer_token_rejected_on_admin_me(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/me')->assertUnauthorized();
    }
}

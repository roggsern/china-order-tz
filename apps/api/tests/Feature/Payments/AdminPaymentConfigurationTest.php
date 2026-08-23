<?php

namespace Tests\Feature\Payments;

use App\Enums\ActivityEventType;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\User;
use App\Services\Payments\PaymentConfigurationResolver;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPaymentConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        Cache::flush();
    }

    public function test_guest_and_customer_cannot_access_payment_config(): void
    {
        $this->getJson('/api/v1/admin/payments/config')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/payments/config')->assertUnauthorized();
        $this->putJson('/api/v1/admin/payments/config', [
            'default_provider' => 'nmb',
        ])->assertUnauthorized();
    }

    public function test_permission_denied_without_payments_config_permissions(): void
    {
        Sanctum::actingAs(Admin::factory()->withoutPermissions()->create());

        $this->getJson('/api/v1/admin/payments/config')->assertForbidden();
        $this->putJson('/api/v1/admin/payments/config', [
            'default_provider' => 'nmb',
        ])->assertForbidden();
    }

    public function test_view_permission_can_read_but_not_update(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::PAYMENTS_CONFIG_VIEW])->create(),
        );

        $this->getJson('/api/v1/admin/payments/config')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.default_provider', 'nmb')
            ->assertJsonPath('data.enabled_methods.nmb', true)
            ->assertJsonPath('data.enabled_methods.mpesa', false);

        $this->putJson('/api/v1/admin/payments/config', [
            'enabled_methods' => ['cash' => true],
        ])->assertForbidden();
    }

    public function test_manage_updates_config_and_writes_audit(): void
    {
        $admin = Admin::factory()->withPermissions([
            AdminPermissions::PAYMENTS_CONFIG_VIEW,
            AdminPermissions::PAYMENTS_CONFIG_MANAGE,
        ])->create();

        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/v1/admin/payments/config', [
            'default_provider' => 'cash',
            'enabled_methods' => [
                'nmb' => true,
                'mpesa' => false,
                'card' => false,
                'cash' => true,
                'bank_transfer' => false,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.default_provider', 'cash')
            ->assertJsonPath('data.enabled_methods.cash', true);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::PaymentConfigurationUpdated->value,
            'actor_id' => $admin->id,
        ]);

        $log = ActivityLog::query()
            ->where('event_type', ActivityEventType::PaymentConfigurationUpdated->value)
            ->latest('created_at')
            ->firstOrFail();

        $this->assertSame('nmb', $log->old_values['default_provider'] ?? null);
        $this->assertSame('cash', $log->new_values['default_provider'] ?? null);
        $this->assertTrue((bool) ($log->new_values['enabled_methods']['cash'] ?? false));
    }

    public function test_rejects_default_provider_when_disabled(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::PAYMENTS_CONFIG_VIEW,
                AdminPermissions::PAYMENTS_CONFIG_MANAGE,
            ])->create(),
        );

        $this->putJson('/api/v1/admin/payments/config', [
            'default_provider' => 'mpesa',
            'enabled_methods' => [
                'nmb' => true,
                'mpesa' => false,
                'card' => false,
                'cash' => false,
                'bank_transfer' => false,
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['default_provider']);
    }

    public function test_rejects_unknown_providers_and_secrets(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::PAYMENTS_CONFIG_VIEW,
                AdminPermissions::PAYMENTS_CONFIG_MANAGE,
            ])->create(),
        );

        $this->putJson('/api/v1/admin/payments/config', [
            'default_provider' => 'paypal',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['default_provider']);

        $this->putJson('/api/v1/admin/payments/config', [
            'enabled_methods' => [
                'nmb' => true,
                'crypto' => true,
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['enabled_methods']);

        $this->putJson('/api/v1/admin/payments/config', [
            'default_provider' => 'nmb',
            'webhook_secret' => 'should-not-store',
        ])->assertStatus(422);
    }

    public function test_resolver_reads_settings_and_provider_availability(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::PAYMENTS_CONFIG_VIEW,
                AdminPermissions::PAYMENTS_CONFIG_MANAGE,
            ])->create(),
        );

        $this->putJson('/api/v1/admin/payments/config', [
            'default_provider' => 'bank_transfer',
            'enabled_methods' => [
                'nmb' => false,
                'mpesa' => false,
                'card' => false,
                'cash' => false,
                'bank_transfer' => true,
            ],
        ])->assertOk();

        $resolver = app(PaymentConfigurationResolver::class);

        $this->assertSame('bank_transfer', $resolver->resolveDefaultProvider());
        $this->assertSame(['bank_transfer'], $resolver->enabledMethodList());
        $this->assertTrue($resolver->isMethodEnabled('bank_transfer'));
        $this->assertFalse($resolver->isMethodEnabled('nmb'));
        $this->assertTrue($resolver->isProviderAvailable('bank_transfer'));
        $this->assertTrue($resolver->isProviderAvailable('cash'));
        $this->assertFalse($resolver->isProviderAvailable('mpesa'));
        $this->assertFalse($resolver->isKnownMethod('paypal'));
    }

    public function test_partial_enabled_methods_update_preserves_snippe_state(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::PAYMENTS_CONFIG_VIEW,
                AdminPermissions::PAYMENTS_CONFIG_MANAGE,
            ])->create(),
        );

        $this->putJson('/api/v1/admin/payments/config', [
            'default_provider' => 'nmb',
            'enabled_methods' => [
                'nmb' => true,
                'snippe' => true,
                'mpesa' => false,
                'card' => false,
                'cash' => false,
                'bank_transfer' => false,
            ],
        ])->assertOk()
            ->assertJsonPath('data.enabled_methods.snippe', true)
            ->assertJsonPath('data.enabled_methods.nmb', true);

        $this->putJson('/api/v1/admin/payments/config', [
            'enabled_methods' => [
                'cash' => true,
            ],
        ])->assertOk()
            ->assertJsonPath('data.enabled_methods.snippe', true)
            ->assertJsonPath('data.enabled_methods.nmb', true)
            ->assertJsonPath('data.enabled_methods.cash', true);

        $this->putJson('/api/v1/admin/payments/config', [
            'enabled_methods' => [
                'snippe' => false,
            ],
        ])->assertOk()
            ->assertJsonPath('data.enabled_methods.snippe', false)
            ->assertJsonPath('data.enabled_methods.nmb', true);
    }
}

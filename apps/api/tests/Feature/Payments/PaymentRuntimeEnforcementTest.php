<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\SettingType;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentRuntimeEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        Cache::flush();

        config([
            'services.nmb.enabled' => true,
            'services.nmb.base_url' => 'https://sandbox.nmb.test',
            'services.nmb.api_version' => '85',
            'services.nmb.merchant_id' => 'TESTMERCHANT',
            'services.nmb.username' => 'merchant.TESTMERCHANT',
            'services.nmb.password' => 'sandbox-password',
            'services.nmb.return_url' => 'https://app.chinaorder.test/payments/return',
            'services.nmb.callback_url' => 'https://api.chinaorder.test/api/v1/payments/nmb/callback',
            'payments.nmb.enabled' => true,
            'payments.nmb.base_url' => 'https://sandbox.nmb.test',
            'payments.nmb.merchant_id' => 'TESTMERCHANT',
            'payments.nmb.password' => 'sandbox-password',
            'payments.orchestrator.default_provider' => 'nmb',
        ]);

        Http::fake([
            'sandbox.nmb.test/*/session' => Http::response([
                'result' => 'SUCCESS',
                'session' => [
                    'id' => 'SESSION-RUNTIME-1',
                    'successIndicator' => 'indicator-runtime',
                    'checkoutUrl' => 'https://checkout.nmb.test/pay/runtime',
                ],
            ]),
            'sandbox.nmb.test/*/order/*' => Http::response([
                'result' => 'PENDING',
                'order' => [
                    'id' => 'pending',
                    'amount' => '0.00',
                    'currency' => 'TZS',
                ],
            ]),
        ]);
    }

    public function test_enabled_provider_can_start_payment(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 25000]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Nmb->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.provider', 'nmb');
    }

    public function test_disabled_provider_cannot_start_payment(): void
    {
        app(SettingsService::class)->set('payments.enabled_methods', [
            'nmb' => false,
            'mpesa' => false,
            'card' => false,
            'cash' => true,
            'bank_transfer' => false,
        ]);
        app(SettingsService::class)->set('payments.default_provider', 'cash');
        Cache::flush();

        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 25000]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Nmb->value,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'payment_failed')
            ->assertJsonValidationErrors(['provider']);
    }

    public function test_unknown_provider_rejected_on_start(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 25000]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => 'not_a_real_provider',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['provider']);

        // Enum-known but unavailable provider is a payment domain rejection.
        $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => 'selcom',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'payment_failed')
            ->assertJsonValidationErrors(['provider']);
    }

    public function test_disabled_default_provider_rejected_when_omitted(): void
    {
        app(SettingsService::class)->set('payments.enabled_methods', [
            'nmb' => false,
            'mpesa' => false,
            'card' => false,
            'cash' => true,
            'bank_transfer' => false,
        ]);
        // Bypass admin write-path so default can be inconsistent with enabled_methods.
        Setting::query()->updateOrCreate(
            ['key' => 'payments.default_provider'],
            [
                'group' => 'payments',
                'type' => SettingType::String,
                'value' => 'nmb',
                'is_active' => true,
            ],
        );
        Cache::flush();

        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 25000]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/start/{$order->id}")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'payment_failed')
            ->assertJsonValidationErrors(['provider']);
    }

    public function test_checkout_methods_filters_to_enabled(): void
    {
        app(SettingsService::class)->set('payments.enabled_methods', [
            'nmb' => true,
            'mpesa' => false,
            'card' => false,
            'cash' => true,
            'bank_transfer' => false,
        ]);
        app(SettingsService::class)->set('payments.default_provider', 'nmb');
        Cache::flush();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/payments/methods')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.default_provider', 'nmb')
            ->assertJsonPath('data.enabled_methods', ['nmb', 'cash'])
            ->assertJsonPath('data.methods.0.code', 'nmb')
            ->assertJsonPath('data.methods.0.enabled', true)
            ->assertJsonPath('data.methods.0.selectable', true)
            ->assertJsonPath('data.methods.1.code', 'snippe')
            ->assertJsonPath('data.methods.1.enabled', false)
            ->assertJsonPath('data.methods.1.selectable', false)
            ->assertJsonPath('data.methods.2.code', 'mpesa')
            ->assertJsonPath('data.methods.2.enabled', false)
            ->assertJsonPath('data.methods.2.selectable', false)
            ->assertJsonPath('data.methods.4.code', 'cash')
            ->assertJsonPath('data.methods.4.enabled', true)
            ->assertJsonPath('data.methods.4.selectable', true);
    }

    public function test_guest_cannot_list_checkout_methods(): void
    {
        $this->getJson('/api/v1/payments/methods')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_disabled_prepare_method_rejected(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 25000]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/orders/{$order->id}/payments", [
            'payment_method' => PaymentMethod::BankTransfer->value,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'payment_failed')
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_enabled_prepare_method_allowed(): void
    {
        app(SettingsService::class)->set('payments.enabled_methods', [
            'nmb' => true,
            'mpesa' => false,
            'card' => false,
            'cash' => false,
            'bank_transfer' => true,
        ]);
        Cache::flush();

        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 25000]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/orders/{$order->id}/payments", [
            'payment_method' => PaymentMethod::BankTransfer->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.payment_method', 'bank_transfer');
    }
}

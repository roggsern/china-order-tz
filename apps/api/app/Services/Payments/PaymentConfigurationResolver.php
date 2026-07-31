<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Services\Settings\SettingsService;
use Throwable;

/**
 * Resolves admin payment availability configuration from Settings (payments group).
 * Secrets stay in ENV; this only reads default provider + enabled method toggles.
 *
 * Does not alter PaymentOrchestrator or NMB provider logic.
 */
final class PaymentConfigurationResolver
{
    public const GROUP = 'payments';

    public const DEFAULT_PROVIDER_KEY = 'default_provider';

    public const ENABLED_METHODS_KEY = 'enabled_methods';

    /** @var list<string> */
    public const MANAGED_METHODS = [
        PaymentMethod::Nmb->value,
        PaymentMethod::Mpesa->value,
        PaymentMethod::Card->value,
        PaymentMethod::Cash->value,
        PaymentMethod::BankTransfer->value,
    ];

    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function resolveDefaultProvider(): string
    {
        try {
            $provider = strtolower(trim((string) $this->settings->get(
                'payments.'.self::DEFAULT_PROVIDER_KEY,
                PaymentMethod::Nmb->value,
            )));
        } catch (Throwable) {
            $provider = PaymentMethod::Nmb->value;
        }

        if (! $this->isKnownMethod($provider)) {
            return PaymentMethod::Nmb->value;
        }

        return $provider;
    }

    /**
     * @return array<string, bool>
     */
    public function resolveEnabledMethods(): array
    {
        try {
            $methods = $this->settings->get('payments.'.self::ENABLED_METHODS_KEY);
        } catch (Throwable) {
            $methods = null;
        }

        if (! is_array($methods)) {
            $methods = $this->defaultEnabledMethods();
        }

        $normalized = $this->defaultEnabledMethods();
        foreach (self::MANAGED_METHODS as $method) {
            if (array_key_exists($method, $methods)) {
                $normalized[$method] = (bool) $methods[$method];
            }
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    public function enabledMethodList(): array
    {
        $enabled = [];
        foreach ($this->resolveEnabledMethods() as $method => $isEnabled) {
            if ($isEnabled) {
                $enabled[] = $method;
            }
        }

        return $enabled;
    }

    public function isMethodEnabled(string $method): bool
    {
        $method = strtolower(trim($method));
        if (! $this->isKnownMethod($method)) {
            return false;
        }

        return (bool) ($this->resolveEnabledMethods()[$method] ?? false);
    }

    public function isKnownMethod(string $method): bool
    {
        return in_array(strtolower(trim($method)), self::MANAGED_METHODS, true);
    }

    /**
     * Infra readiness from ENV/config only — never reads or returns secret values.
     */
    public function isProviderAvailable(string $method): bool
    {
        $method = strtolower(trim($method));

        if (! $this->isKnownMethod($method)) {
            return false;
        }

        return match ($method) {
            PaymentMethod::Nmb->value => $this->isNmbAvailable(),
            PaymentMethod::Cash->value, PaymentMethod::BankTransfer->value => true,
            PaymentMethod::Mpesa->value, PaymentMethod::Card->value => false,
            default => false,
        };
    }

    /**
     * Default provider must be known and enabled in settings.
     */
    public function validateProviderAvailability(string $provider): void
    {
        $this->assertCheckoutMethodAllowed($provider, 'default_provider', true);
    }

    /**
     * Resolve the provider used for payment start.
     * Uses settings default when request omits provider; rejects unknown/disabled methods.
     */
    public function resolveStartProvider(?string $provider = null): string
    {
        $resolved = $provider !== null && trim($provider) !== ''
            ? strtolower(trim($provider))
            : $this->resolveDefaultProvider();

        $this->assertCheckoutMethodAllowed(
            $resolved,
            'provider',
            $provider === null || trim((string) $provider) === '',
        );

        return $resolved;
    }

    /**
     * Reject unknown or disabled checkout payment methods.
     *
     * @param  non-empty-string  $field
     */
    public function assertCheckoutMethodAllowed(
        string $method,
        string $field = 'provider',
        bool $asDefault = false,
    ): void {
        $method = strtolower(trim($method));

        if (! $this->isKnownMethod($method)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $field => ["Unknown payment provider [{$method}]."],
            ]);
        }

        if (! $this->isMethodEnabled($method)) {
            $message = $asDefault
                ? 'Default provider must be enabled in enabled_methods.'
                : "Payment provider [{$method}] is disabled.";

            throw \Illuminate\Validation\ValidationException::withMessages([
                $field => [$message],
            ]);
        }
    }

    /**
     * Customer checkout availability — enabled toggles + ENV readiness (no secrets).
     *
     * @return array{
     *     default_provider: string,
     *     enabled_methods: list<string>,
     *     methods: list<array{code: string, enabled: bool, available: bool, selectable: bool}>
     * }
     */
    public function presentCheckoutAvailability(): array
    {
        $enabled = $this->resolveEnabledMethods();
        $methods = [];

        foreach (self::MANAGED_METHODS as $method) {
            $isEnabled = (bool) ($enabled[$method] ?? false);
            $available = $this->isProviderAvailable($method);

            $methods[] = [
                'code' => $method,
                'enabled' => $isEnabled,
                'available' => $available,
                'selectable' => $isEnabled && $available,
            ];
        }

        return [
            'default_provider' => $this->resolveDefaultProvider(),
            'enabled_methods' => $this->enabledMethodList(),
            'methods' => $methods,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentConfig(): array
    {
        $enabled = $this->resolveEnabledMethods();
        $default = $this->resolveDefaultProvider();

        $providerStatus = [];
        foreach (self::MANAGED_METHODS as $method) {
            $providerStatus[$method] = [
                'enabled' => (bool) ($enabled[$method] ?? false),
                'available' => $this->isProviderAvailable($method),
            ];
        }

        return [
            'default_provider' => $default,
            'enabled_methods' => $enabled,
            'provider_status' => $providerStatus,
            'managed_methods' => self::MANAGED_METHODS,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'default_provider' => $this->resolveDefaultProvider(),
            'enabled_methods' => $this->resolveEnabledMethods(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function defaultEnabledMethods(): array
    {
        return [
            PaymentMethod::Nmb->value => true,
            PaymentMethod::Mpesa->value => false,
            PaymentMethod::Card->value => false,
            PaymentMethod::Cash->value => false,
            PaymentMethod::BankTransfer->value => false,
        ];
    }

    private function isNmbAvailable(): bool
    {
        $enabled = (bool) config('payments.nmb.enabled', config('services.nmb.enabled', false));
        $merchantId = trim((string) config('payments.nmb.merchant_id', config('services.nmb.merchant_id', '')));
        $baseUrl = trim((string) config('payments.nmb.base_url', config('services.nmb.base_url', '')));

        return $enabled && $merchantId !== '' && $baseUrl !== '';
    }
}

<?php

namespace App\Services\Payments;

use App\Events\Audit\PaymentConfigurationUpdatedAudit;
use App\Models\Admin;
use App\Services\Settings\SettingsService;
use App\Support\Settings\SettingsSecretGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin write path for payment availability settings (Settings group payments).
 * Never accepts or persists provider secrets.
 */
final class PaymentConfigurationService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly PaymentConfigurationResolver $resolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->resolver->presentConfig();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateConfig(array $payload, ?Admin $actor = null): array
    {
        $this->rejectSecretPayload($payload);

        $values = $this->normalizeUpdatePayload($payload);
        if ($values === []) {
            throw ValidationException::withMessages([
                'config' => ['At least one of default_provider or enabled_methods is required.'],
            ]);
        }

        $nextMethods = $values[PaymentConfigurationResolver::ENABLED_METHODS_KEY]
            ?? $this->resolver->resolveEnabledMethods();
        $nextDefault = $values[PaymentConfigurationResolver::DEFAULT_PROVIDER_KEY]
            ?? $this->resolver->resolveDefaultProvider();

        $this->assertDefaultEnabled($nextDefault, $nextMethods);

        return DB::transaction(function () use ($values, $nextDefault, $nextMethods, $actor) {
            $before = $this->resolver->snapshot();

            if (isset($values[PaymentConfigurationResolver::ENABLED_METHODS_KEY])) {
                $this->settings->set(
                    'payments.'.PaymentConfigurationResolver::ENABLED_METHODS_KEY,
                    $nextMethods,
                    $actor,
                );
            }

            if (isset($values[PaymentConfigurationResolver::DEFAULT_PROVIDER_KEY])
                || $before['default_provider'] !== $nextDefault) {
                $this->settings->set(
                    'payments.'.PaymentConfigurationResolver::DEFAULT_PROVIDER_KEY,
                    $nextDefault,
                    $actor,
                );
            }

            $after = $this->resolver->snapshot();
            event(PaymentConfigurationUpdatedAudit::fromChange($before, $after, $actor));

            return $this->resolver->presentConfig();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeUpdatePayload(array $payload): array
    {
        $values = [];

        if (array_key_exists('default_provider', $payload)) {
            $provider = strtolower(trim((string) $payload['default_provider']));
            if (! $this->resolver->isKnownMethod($provider)) {
                throw ValidationException::withMessages([
                    'default_provider' => ["Unknown payment provider [{$provider}]."],
                ]);
            }
            $values[PaymentConfigurationResolver::DEFAULT_PROVIDER_KEY] = $provider;
        }

        if (array_key_exists('enabled_methods', $payload)) {
            $values[PaymentConfigurationResolver::ENABLED_METHODS_KEY] = $this->validateEnabledMethods(
                $payload['enabled_methods'],
            );
        }

        return $values;
    }

    /**
     * @param  array<string, bool>  $methods
     */
    private function assertDefaultEnabled(string $default, array $methods): void
    {
        if (! $this->resolver->isKnownMethod($default)) {
            throw ValidationException::withMessages([
                'default_provider' => ["Unknown payment provider [{$default}]."],
            ]);
        }

        if (! ($methods[$default] ?? false)) {
            throw ValidationException::withMessages([
                'default_provider' => ['Default provider must be enabled in enabled_methods.'],
            ]);
        }
    }

    /**
     * @return array<string, bool>
     */
    private function validateEnabledMethods(mixed $methods): array
    {
        if (! is_array($methods)) {
            throw ValidationException::withMessages([
                'enabled_methods' => ['Enabled methods must be an object of provider keys to booleans.'],
            ]);
        }

        $normalized = $this->resolver->resolveEnabledMethods();

        foreach ($methods as $method => $enabled) {
            if (! is_string($method)) {
                throw ValidationException::withMessages([
                    'enabled_methods' => ['Payment method keys must be strings.'],
                ]);
            }

            $key = strtolower(trim($method));
            if (! $this->resolver->isKnownMethod($key)) {
                throw ValidationException::withMessages([
                    'enabled_methods' => ["Unknown payment provider [{$method}]."],
                ]);
            }

            $normalized[$key] = (bool) $enabled;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function rejectSecretPayload(array $payload): void
    {
        $stack = [$payload];
        while ($stack !== []) {
            $current = array_pop($stack);
            if (! is_array($current)) {
                continue;
            }
            foreach ($current as $key => $value) {
                if (is_string($key) && SettingsSecretGuard::isSecretKey($key)) {
                    throw ValidationException::withMessages([
                        $key => ['Payment secrets cannot be stored in configuration. Keep API keys and merchant secrets in ENV only.'],
                    ]);
                }
                if (is_array($value)) {
                    $stack[] = $value;
                }
            }
        }
    }
}

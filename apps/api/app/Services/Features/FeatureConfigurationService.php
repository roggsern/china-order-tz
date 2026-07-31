<?php

namespace App\Services\Features;

use App\Events\Audit\FeatureConfigurationUpdatedAudit;
use App\Models\Admin;
use App\Services\Settings\SettingsService;
use App\Support\Settings\SettingsSecretGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin write path for feature flags + maintenance mode (Settings group features).
 */
final class FeatureConfigurationService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly FeatureFlagResolver $flags,
        private readonly MaintenanceModeResolver $maintenance,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->present();
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
                'config' => ['At least one of maintenance_mode, maintenance_message, or flags is required.'],
            ]);
        }

        return DB::transaction(function () use ($values, $actor) {
            $before = $this->snapshot();

            foreach ($values as $shortKey => $value) {
                $this->settings->set('features.'.$shortKey, $value, $actor);
            }

            $after = $this->snapshot();
            event(FeatureConfigurationUpdatedAudit::fromChange($before, $after, $actor));

            return $this->present();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $status = $this->maintenance->status();

        return [
            'maintenance_mode' => $status['enabled'],
            'maintenance_message' => $status['message'],
            'flags' => $this->flags->resolveFlags(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(): array
    {
        $status = $this->maintenance->status();

        return [
            'maintenance_mode' => $status['enabled'],
            'maintenance_message' => $status['message'],
            'flags' => $this->flags->resolveFlags(),
            'allowed_flags' => FeatureFlagResolver::ALLOWED_FLAGS,
            'enabled_features' => $this->flags->enabledFeatures(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeUpdatePayload(array $payload): array
    {
        $values = [];

        if (array_key_exists('maintenance_mode', $payload)) {
            $values[MaintenanceModeResolver::MODE_KEY] = (bool) $payload['maintenance_mode'];
        }

        if (array_key_exists('maintenance_message', $payload)) {
            $message = $payload['maintenance_message'];
            if ($message !== null && ! is_string($message)) {
                throw ValidationException::withMessages([
                    'maintenance_message' => ['Maintenance message must be a string.'],
                ]);
            }
            $values[MaintenanceModeResolver::MESSAGE_KEY] = trim((string) ($message ?? ''));
        }

        if (array_key_exists('flags', $payload)) {
            $values[FeatureFlagResolver::FLAGS_KEY] = $this->validateFlags($payload['flags']);
        }

        return $values;
    }

    /**
     * @return array<string, bool>
     */
    private function validateFlags(mixed $flags): array
    {
        if (! is_array($flags)) {
            throw ValidationException::withMessages([
                'flags' => ['Flags must be an object of flag keys to booleans.'],
            ]);
        }

        $normalized = $this->flags->defaultFlags();

        foreach ($flags as $flag => $enabled) {
            if (! is_string($flag)) {
                throw ValidationException::withMessages([
                    'flags' => ['Feature flag keys must be strings.'],
                ]);
            }

            $key = strtolower(trim($flag));

            if ($this->flags->isForbiddenFlag($key)) {
                throw ValidationException::withMessages([
                    'flags' => ["Feature flag [{$flag}] cannot control core security or commerce rules."],
                ]);
            }

            if (! $this->flags->isAllowedFlag($key)) {
                throw ValidationException::withMessages([
                    'flags' => ["Unknown or unsupported feature flag [{$flag}]."],
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
                        $key => ['Secrets cannot be stored in feature configuration.'],
                    ]);
                }
                if (is_array($value)) {
                    $stack[] = $value;
                }
            }
        }
    }
}

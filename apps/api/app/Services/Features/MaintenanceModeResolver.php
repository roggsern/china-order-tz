<?php

namespace App\Services\Features;

use App\Services\Settings\SettingsService;
use Throwable;

/**
 * Resolves maintenance mode status and message from Settings (features group).
 */
final class MaintenanceModeResolver
{
    public const MODE_KEY = 'maintenance_mode';

    public const MESSAGE_KEY = 'maintenance_message';

    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function isEnabled(): bool
    {
        try {
            return (bool) $this->settings->get('features.'.self::MODE_KEY, false);
        } catch (Throwable) {
            return false;
        }
    }

    public function message(): string
    {
        try {
            $message = $this->settings->get('features.'.self::MESSAGE_KEY, '');
        } catch (Throwable) {
            return '';
        }

        if ($message === null) {
            return '';
        }

        return trim((string) $message);
    }

    /**
     * @return array{enabled: bool, message: string}
     */
    public function status(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'message' => $this->message(),
        ];
    }

    public function defaultPublicMessage(): string
    {
        return 'The store is temporarily unavailable for maintenance. Please try again shortly.';
    }

    /**
     * Customer-safe message — never exposes settings keys or admin config.
     */
    public function publicMessage(): string
    {
        $message = $this->message();

        return $message !== '' ? $message : $this->defaultPublicMessage();
    }

    /**
     * Public probe payload for storefront/status consumers.
     *
     * @return array{maintenance: bool, message: string|null}
     */
    public function publicStatus(): array
    {
        $enabled = $this->isEnabled();

        return [
            'maintenance' => $enabled,
            'message' => $enabled ? $this->publicMessage() : null,
        ];
    }

    /**
     * Consistent blocked-storefront API body.
     *
     * @return array{success: false, maintenance: true, code: string, message: string}
     */
    public function blockedResponsePayload(): array
    {
        return [
            'success' => false,
            'maintenance' => true,
            'code' => 'maintenance_mode',
            'message' => $this->publicMessage(),
        ];
    }
}

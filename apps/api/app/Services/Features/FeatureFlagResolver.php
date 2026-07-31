<?php

namespace App\Services\Features;

use App\Services\Settings\SettingsService;
use Throwable;

/**
 * Resolves optional UX/product feature flags from Settings (features.flags).
 * Must never gate payment verification, inventory reservation, permissions, or order lifecycle.
 */
final class FeatureFlagResolver
{
    public const GROUP = 'features';

    public const FLAGS_KEY = 'flags';

    /** @var list<string> */
    public const ALLOWED_FLAGS = [
        'wishlist',
        'reviews',
        'new_checkout',
    ];

    /**
     * Flag key fragments that must never be accepted (core security / commerce).
     *
     * @var list<string>
     */
    public const FORBIDDEN_FLAG_FRAGMENTS = [
        'payment_verification',
        'payment_verify',
        'inventory_reservation',
        'inventory_reserve',
        'permission',
        'rbac',
        'order_lifecycle',
        'order_engine',
        'fulfillment_engine',
        'checkout_payment',
    ];

    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return array<string, bool>
     */
    public function resolveFlags(): array
    {
        try {
            $flags = $this->settings->get('features.'.self::FLAGS_KEY);
        } catch (Throwable) {
            $flags = null;
        }

        if (! is_array($flags)) {
            return $this->defaultFlags();
        }

        $normalized = $this->defaultFlags();
        foreach (self::ALLOWED_FLAGS as $flag) {
            if (array_key_exists($flag, $flags)) {
                $normalized[$flag] = (bool) $flags[$flag];
            }
        }

        return $normalized;
    }

    public function isEnabled(string $flag): bool
    {
        $flag = strtolower(trim($flag));
        if (! in_array($flag, self::ALLOWED_FLAGS, true)) {
            return false;
        }

        return (bool) ($this->resolveFlags()[$flag] ?? false);
    }

    /**
     * @return list<string>
     */
    public function enabledFeatures(): array
    {
        $enabled = [];
        foreach ($this->resolveFlags() as $flag => $isEnabled) {
            if ($isEnabled) {
                $enabled[] = $flag;
            }
        }

        return $enabled;
    }

    public function isAllowedFlag(string $flag): bool
    {
        return in_array(strtolower(trim($flag)), self::ALLOWED_FLAGS, true);
    }

    public function isForbiddenFlag(string $flag): bool
    {
        $normalized = strtolower(str_replace(['-', '.'], '_', trim($flag)));

        foreach (self::FORBIDDEN_FLAG_FRAGMENTS as $fragment) {
            if ($normalized === $fragment || str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, bool>
     */
    public function defaultFlags(): array
    {
        return [
            'wishlist' => false,
            'reviews' => false,
            'new_checkout' => false,
        ];
    }
}

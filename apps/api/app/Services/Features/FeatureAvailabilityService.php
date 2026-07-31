<?php

namespace App\Services\Features;

use App\Exceptions\FeatureDisabledException;

/**
 * Runtime feature availability — all reads delegate to FeatureFlagResolver.
 */
final class FeatureAvailabilityService
{
    public const WISHLIST = 'wishlist';

    public const REVIEWS = 'reviews';

    public const NEW_CHECKOUT = 'new_checkout';

    public function __construct(
        private readonly FeatureFlagResolver $flags,
    ) {}

    public function isEnabled(string $feature): bool
    {
        return $this->flags->isEnabled($feature);
    }

    public function canUseWishlist(): bool
    {
        return $this->isEnabled(self::WISHLIST);
    }

    public function canUseReviews(): bool
    {
        return $this->isEnabled(self::REVIEWS);
    }

    public function canUseNewCheckout(): bool
    {
        return $this->isEnabled(self::NEW_CHECKOUT);
    }

    /**
     * Public-safe feature flags (no admin or security settings).
     *
     * @return array{wishlist: bool, reviews: bool, new_checkout: bool}
     */
    public function publicFlags(): array
    {
        $resolved = $this->flags->resolveFlags();

        return [
            self::WISHLIST => (bool) ($resolved[self::WISHLIST] ?? false),
            self::REVIEWS => (bool) ($resolved[self::REVIEWS] ?? false),
            self::NEW_CHECKOUT => (bool) ($resolved[self::NEW_CHECKOUT] ?? false),
        ];
    }

    /**
     * @throws FeatureDisabledException
     */
    public function assertEnabled(string $feature): void
    {
        if (! $this->isEnabled($feature)) {
            throw FeatureDisabledException::for($feature);
        }
    }

    public function assertWishlist(): void
    {
        $this->assertEnabled(self::WISHLIST);
    }

    public function assertReviews(): void
    {
        $this->assertEnabled(self::REVIEWS);
    }
}

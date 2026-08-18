<?php

namespace App\Services\Storefront;

use Illuminate\Support\Facades\Cache;

/**
 * Public, non-personalized China mega-menu response cache.
 *
 * Invalidation: generation bump via ChinaStorefrontDiscoveryCache (product / sellability
 * writes) plus TTL expiry (default 120s). Safe for shared storefront data only —
 * never put auth/customer fields in this payload.
 */
final class ChinaStorefrontMenuCache
{
    public const TTL_SECONDS = 120;

    public const KEY_PREFIX = 'storefront:china:menu:v7:';

    public function __construct(
        private readonly ChinaStorefrontDiscoveryCache $discoveryCache,
    ) {}

    public function key(?string $categorySlug): string
    {
        $slug = is_string($categorySlug) ? trim($categorySlug) : '';

        return self::KEY_PREFIX.$this->discoveryCache->generation().':'
            .($slug !== '' ? $slug : '__root__');
    }

    /**
     * @template T
     *
     * @param  callable(): T  $resolver
     * @return T
     */
    public function remember(?string $categorySlug, callable $resolver): mixed
    {
        return Cache::remember($this->key($categorySlug), self::TTL_SECONDS, $resolver);
    }

    public function forget(?string $categorySlug = null): void
    {
        if ($categorySlug === null) {
            Cache::forget($this->key(null));

            return;
        }

        Cache::forget($this->key($categorySlug));
    }
}

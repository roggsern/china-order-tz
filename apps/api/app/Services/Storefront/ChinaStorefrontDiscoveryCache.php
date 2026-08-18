<?php

namespace App\Services\Storefront;

use Illuminate\Support\Facades\Cache;

/**
 * Generation token for CHINA_IMPORT storefront discovery caches.
 *
 * Bumping the generation retires prior menu / featured-collection / China product-list
 * keys without a global Redis flush. Public TZ/homepage buckets are unchanged.
 */
final class ChinaStorefrontDiscoveryCache
{
    public const GENERATION_KEY = 'storefront:china:discovery:generation';

    public function generation(): int
    {
        $value = Cache::get(self::GENERATION_KEY, 1);

        return max(1, (int) $value);
    }

    public function bump(): int
    {
        if (! Cache::has(self::GENERATION_KEY)) {
            Cache::forever(self::GENERATION_KEY, 1);
        }

        $next = (int) Cache::increment(self::GENERATION_KEY);

        return max(1, $next);
    }
}

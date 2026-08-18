<?php

namespace App\Models\Concerns;

use App\Services\Storefront\ChinaStorefrontDiscoveryCache;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Centralized CHINA_IMPORT storefront cache invalidation on catalog write paths.
 */
trait InvalidatesChinaStorefrontDiscovery
{
    protected static function bootInvalidatesChinaStorefrontDiscovery(): void
    {
        $invalidate = static function (): void {
            try {
                app(ChinaStorefrontDiscoveryCache::class)->bump();
            } catch (\Throwable) {
                // Never break product/inventory writes because of cache.
            }
        };

        static::saved($invalidate);
        static::deleted($invalidate);

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored($invalidate);
        }
    }
}

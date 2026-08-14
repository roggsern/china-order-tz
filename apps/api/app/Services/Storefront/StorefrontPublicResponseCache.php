<?php

namespace App\Services\Storefront;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Shared short-TTL cache for public, non-personalized storefront list responses.
 *
 * Safe for homepage rails and anonymous catalog browse — never include auth/customer data.
 * Invalidation: TTL expiry (default 120s).
 */
final class StorefrontPublicResponseCache
{
    public const TTL_SECONDS = 120;

    public const KEY_PREFIX = 'storefront:public:v2:';

    public function key(string $bucket, string $variant): string
    {
        return self::KEY_PREFIX.$bucket.':'.hash('xxh3', $variant);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $resolver
     * @return T
     */
    public function remember(string $bucket, string $variant, callable $resolver): mixed
    {
        return Cache::remember($this->key($bucket, $variant), self::TTL_SECONDS, $resolver);
    }

    public function forget(string $bucket, string $variant): void
    {
        Cache::forget($this->key($bucket, $variant));
    }

    /**
     * Public product/catalog list queries safe to share across anonymous shoppers.
     * Excludes search (personalized ranking / high cardinality).
     */
    public function isCacheableProductList(Request $request): bool
    {
        $search = trim((string) $request->query('search', ''));

        return $search === '';
    }

    public function productListVariant(Request $request): string
    {
        $params = [
            'page' => (string) $request->query('page', '1'),
            'per_page' => (string) $request->query('per_page', '15'),
            'featured' => (string) $request->query('featured', ''),
            'category' => (string) $request->query('category', ''),
            'brand' => (string) $request->query('brand', ''),
            'store' => (string) $request->query('store', ''),
            'commerce_channel' => (string) $request->query('commerce_channel', ''),
            'origin' => (string) $request->query('origin', ''),
            'product_condition' => (string) $request->query('product_condition', ''),
        ];

        ksort($params);

        return http_build_query($params);
    }

    public function chinaProductListVariant(Request $request): string
    {
        $params = [
            'page' => (string) $request->query('page', '1'),
            'per_page' => (string) $request->query('per_page', '12'),
            'featured' => (string) $request->query('featured', ''),
            'category' => (string) $request->query('category', ''),
            'brand' => (string) $request->query('brand', ''),
            'search' => (string) $request->query('search', ''),
        ];

        ksort($params);

        return http_build_query($params);
    }
}

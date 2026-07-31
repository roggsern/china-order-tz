<?php

namespace App\Services\Shipping;

use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use Illuminate\Support\Carbon;

/**
 * Canonical shipping duration resolver.
 * Reads windows from shipping_rates (shipping_methods SSoT) — does not calculate freight price.
 */
class ShippingDurationResolver
{
    public const CODE_AIR = 'air_freight';

    public const CODE_SEA = 'sea_freight';

    public const CODE_LOCAL = 'local_delivery';

    /**
     * Fallback windows when rates are missing (aligned with storefront deliveryEstimate).
     *
     * @var array<string, array{min_days: int, max_days: int, typical_days: int}>
     */
    private const FALLBACKS = [
        self::CODE_AIR => ['min_days' => 7, 'max_days' => 12, 'typical_days' => 10],
        self::CODE_SEA => ['min_days' => 35, 'max_days' => 45, 'typical_days' => 40],
        self::CODE_LOCAL => ['min_days' => 1, 'max_days' => 5, 'typical_days' => 2],
    ];

    /**
     * @return array{min_days: int, max_days: int, typical_days: int, source: string, method_code: string}
     */
    public function resolveAir(): array
    {
        return $this->resolve(self::CODE_AIR);
    }

    /**
     * @return array{min_days: int, max_days: int, typical_days: int, source: string, method_code: string}
     */
    public function resolveSea(): array
    {
        return $this->resolve(self::CODE_SEA);
    }

    /**
     * @return array{min_days: int, max_days: int, typical_days: int, source: string, method_code: string}
     */
    public function resolveLocal(): array
    {
        return $this->resolve(self::CODE_LOCAL);
    }

    /**
     * @return array{
     *   air: array{min_days: int, max_days: int, typical_days: int, source: string, method_code: string},
     *   sea: array{min_days: int, max_days: int, typical_days: int, source: string, method_code: string},
     *   local: array{min_days: int, max_days: int, typical_days: int, source: string, method_code: string}
     * }
     */
    public function resolveAll(): array
    {
        return [
            'air' => $this->resolveAir(),
            'sea' => $this->resolveSea(),
            'local' => $this->resolveLocal(),
        ];
    }

    /**
     * @return array{min_days: int, max_days: int, typical_days: int, source: string, method_code: string}
     */
    public function resolve(string $methodCode): array
    {
        $code = strtolower(trim($methodCode));
        $rate = $this->findActiveRate($code);

        if ($rate === null) {
            return $this->fallback($code);
        }

        return $this->mapRate($rate, $code, 'shipping_rates');
    }

    /**
     * Map cart/order shipping mode (`air`/`sea`) or method code to a duration window.
     *
     * @return array{min_days: int, max_days: int, typical_days: int, source: string, method_code: string}|null
     */
    public function resolveForShippingMode(?string $mode): ?array
    {
        if ($mode === null || trim($mode) === '') {
            return null;
        }

        return match (strtolower(trim($mode))) {
            'air', self::CODE_AIR => $this->resolveAir(),
            'sea', self::CODE_SEA => $this->resolveSea(),
            'local', self::CODE_LOCAL => $this->resolveLocal(),
            default => null,
        };
    }

    private function findActiveRate(string $methodCode): ?ShippingRate
    {
        $method = ShippingMethod::query()
            ->where('code', $methodCode)
            ->where('is_active', true)
            ->first();

        if ($method === null) {
            return null;
        }

        $now = Carbon::now();

        $baseline = ShippingRate::query()
            ->where('shipping_method_id', $method->id)
            ->where('is_active', true)
            ->whereNull('min_weight')
            ->whereNull('max_weight')
            ->where(function ($query) use ($now): void {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $now);
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('created_at')
            ->first();

        if ($baseline !== null) {
            return $baseline;
        }

        return ShippingRate::query()
            ->where('shipping_method_id', $method->id)
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * @return array{min_days: int, max_days: int, typical_days: int, source: string, method_code: string}
     */
    private function mapRate(ShippingRate $rate, string $methodCode, string $source): array
    {
        $typical = $rate->estimated_delivery_days !== null
            ? max(0, (int) $rate->estimated_delivery_days)
            : null;

        $min = $rate->estimated_min_days !== null
            ? max(0, (int) $rate->estimated_min_days)
            : $typical;

        $max = $rate->estimated_max_days !== null
            ? max(0, (int) $rate->estimated_max_days)
            : $typical;

        if ($min === null && $max === null) {
            return $this->fallback($methodCode);
        }

        $min = $min ?? $max ?? 0;
        $max = $max ?? $min;
        if ($max < $min) {
            [$min, $max] = [$max, $min];
        }

        $typical = $typical ?? (int) round(($min + $max) / 2);

        return [
            'min_days' => $min,
            'max_days' => $max,
            'typical_days' => $typical,
            'source' => $source,
            'method_code' => $methodCode,
        ];
    }

    /**
     * @return array{min_days: int, max_days: int, typical_days: int, source: string, method_code: string}
     */
    private function fallback(string $methodCode): array
    {
        $window = self::FALLBACKS[$methodCode] ?? self::FALLBACKS[self::CODE_LOCAL];

        return [
            'min_days' => $window['min_days'],
            'max_days' => $window['max_days'],
            'typical_days' => $window['typical_days'],
            'source' => 'fallback',
            'method_code' => array_key_exists($methodCode, self::FALLBACKS)
                ? $methodCode
                : self::CODE_LOCAL,
        ];
    }
}

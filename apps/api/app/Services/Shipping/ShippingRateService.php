<?php

namespace App\Services\Shipping;

use App\Events\Audit\ShippingRateUpdatedAudit;
use App\Models\Admin;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin management for platform shipping_rates (SSoT for price + duration windows).
 * Does not alter ShippingDurationResolver contract.
 */
final class ShippingRateService
{
    /** @var list<string> */
    public const MANAGED_CODES = [
        ShippingDurationResolver::CODE_AIR,
        ShippingDurationResolver::CODE_SEA,
        ShippingDurationResolver::CODE_LOCAL,
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function listRates(): array
    {
        $methods = ShippingMethod::query()
            ->whereIn('code', self::MANAGED_CODES)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->keyBy('code');

        $rows = [];
        foreach (self::MANAGED_CODES as $code) {
            $method = $methods->get($code);
            if ($method === null) {
                continue;
            }
            $rate = $this->resolveBaselineRate($method);
            $rows[] = $this->present($method, $rate);
        }

        return $rows;
    }

    /**
     * @param  array{
     *   price?: mixed,
     *   estimated_min_days?: mixed,
     *   estimated_max_days?: mixed,
     *   estimated_delivery_days?: mixed,
     *   active?: mixed
     * }  $data
     * @return array<string, mixed>
     */
    public function updateRate(ShippingMethod $method, array $data, ?Admin $actor = null): array
    {
        if (! in_array($method->code, self::MANAGED_CODES, true)) {
            throw ValidationException::withMessages([
                'shippingMethod' => ['Only air_freight, sea_freight, and local_delivery can be managed here.'],
            ]);
        }

        return DB::transaction(function () use ($method, $data, $actor) {
            $rate = $this->resolveBaselineRate($method, createIfMissing: true);
            /** @var ShippingRate $locked */
            $locked = ShippingRate::query()->whereKey($rate->id)->lockForUpdate()->firstOrFail();

            $before = $this->snapshot($locked);

            $min = array_key_exists('estimated_min_days', $data)
                ? (int) $data['estimated_min_days']
                : (int) ($locked->estimated_min_days ?? 0);
            $typical = array_key_exists('estimated_delivery_days', $data)
                ? (int) $data['estimated_delivery_days']
                : (int) ($locked->estimated_delivery_days ?? 0);
            $max = array_key_exists('estimated_max_days', $data)
                ? (int) $data['estimated_max_days']
                : (int) ($locked->estimated_max_days ?? 0);

            $this->assertValidDurationWindow($min, $typical, $max);

            if (array_key_exists('price', $data)) {
                $locked->base_cost = number_format((float) $data['price'], 2, '.', '');
            }
            if (array_key_exists('estimated_min_days', $data)) {
                $locked->estimated_min_days = $min;
            }
            if (array_key_exists('estimated_max_days', $data)) {
                $locked->estimated_max_days = $max;
            }
            if (array_key_exists('estimated_delivery_days', $data)) {
                $locked->estimated_delivery_days = $typical;
            }
            if (array_key_exists('active', $data)) {
                $locked->is_active = (bool) $data['active'];
            }

            // Re-validate final window even when only price/active changed.
            $this->assertValidDurationWindow(
                (int) ($locked->estimated_min_days ?? 0),
                (int) ($locked->estimated_delivery_days ?? 0),
                (int) ($locked->estimated_max_days ?? 0),
            );

            $locked->save();
            $fresh = $locked->fresh() ?? $locked;
            $after = $this->snapshot($fresh);

            event(ShippingRateUpdatedAudit::fromRate($method, $fresh, $before, $after, $actor));

            return $this->present($method, $fresh);
        });
    }

    public function resolveManagedMethod(string $codeOrId): ShippingMethod
    {
        $method = ShippingMethod::query()
            ->where(function ($query) use ($codeOrId): void {
                $query->where('code', $codeOrId)->orWhere('id', $codeOrId);
            })
            ->first();

        if ($method === null || ! in_array($method->code, self::MANAGED_CODES, true)) {
            abort(404, 'Shipping method not found.');
        }

        return $method;
    }

    private function resolveBaselineRate(ShippingMethod $method, bool $createIfMissing = false): ShippingRate
    {
        $rate = ShippingRate::query()
            ->where('shipping_method_id', $method->id)
            ->whereNull('min_weight')
            ->whereNull('max_weight')
            ->orderByDesc('is_active')
            ->orderByDesc('effective_from')
            ->orderByDesc('created_at')
            ->first();

        if ($rate !== null) {
            return $rate;
        }

        if (! $createIfMissing) {
            return new ShippingRate([
                'shipping_method_id' => $method->id,
                'base_cost' => '0.00',
                'currency' => 'TZS',
                'is_active' => false,
                'estimated_min_days' => null,
                'estimated_max_days' => null,
                'estimated_delivery_days' => null,
            ]);
        }

        return ShippingRate::query()->create([
            'shipping_method_id' => $method->id,
            'base_cost' => '0.00',
            'currency' => 'TZS',
            'is_active' => true,
            'estimated_min_days' => 1,
            'estimated_max_days' => 1,
            'estimated_delivery_days' => 1,
        ]);
    }

    private function assertValidDurationWindow(int $min, int $typical, int $max): void
    {
        if ($min < 0 || $typical < 0 || $max < 0) {
            throw ValidationException::withMessages([
                'estimated_min_days' => ['Delivery days must be zero or greater.'],
            ]);
        }

        if (! ($min <= $typical && $typical <= $max)) {
            throw ValidationException::withMessages([
                'estimated_delivery_days' => [
                    'Estimated delivery days must satisfy estimated_min_days <= estimated_delivery_days <= estimated_max_days.',
                ],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(ShippingRate $rate): array
    {
        return [
            'price' => $rate->base_cost !== null ? (string) $rate->base_cost : null,
            'estimated_min_days' => $rate->estimated_min_days,
            'estimated_max_days' => $rate->estimated_max_days,
            'estimated_delivery_days' => $rate->estimated_delivery_days,
            'active' => (bool) $rate->is_active,
            'currency' => $rate->currency,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ShippingMethod $method, ShippingRate $rate): array
    {
        return [
            'method' => $method->code,
            'method_name' => $method->name,
            'price' => $rate->base_cost !== null ? (float) $rate->base_cost : 0.0,
            'currency' => $rate->currency ?: 'TZS',
            'estimated_min_days' => $rate->estimated_min_days !== null ? (int) $rate->estimated_min_days : null,
            'estimated_max_days' => $rate->estimated_max_days !== null ? (int) $rate->estimated_max_days : null,
            'estimated_delivery_days' => $rate->estimated_delivery_days !== null
                ? (int) $rate->estimated_delivery_days
                : null,
            'active' => (bool) $rate->is_active,
            'shipping_rate_id' => $rate->id,
        ];
    }
}

<?php

namespace App\Services\Growth;

use App\Enums\CustomerLifecycleStatus;
use App\Enums\GrowthStage;
use App\Events\Audit\GrowthPlatformAudit;
use App\Models\CustomerProfile;
use App\Models\GrowthSegment;
use App\Models\GrowthSegmentMember;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Configurable dynamic segments over CRM metrics / tags / loyalty / orders.
 */
class SegmentEngine
{
    /**
     * @param  array{code?: string, name: string, description?: string|null, rules: array, is_active?: bool, store_id?: string|null}  $data
     */
    public function create(array $data, ?\App\Models\Admin $admin = null): GrowthSegment
    {
        $this->assertRules($data['rules'] ?? []);

        $segment = GrowthSegment::query()->create([
            'code' => filled($data['code'] ?? null)
                ? Str::upper(Str::slug((string) $data['code'], '_'))
                : Str::upper(Str::slug($data['name'], '_')),
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'rules' => $data['rules'],
            'is_active' => $data['is_active'] ?? true,
            'store_id' => $data['store_id'] ?? null,
            'created_by' => $admin?->id,
        ]);

        $this->refreshMembers($segment);
        event(GrowthPlatformAudit::segmentCreated($segment->fresh() ?? $segment, $admin));

        return $segment->fresh() ?? $segment;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(GrowthSegment $segment, array $data): GrowthSegment
    {
        if (array_key_exists('rules', $data)) {
            $this->assertRules($data['rules'] ?? []);
            $segment->rules = $data['rules'];
        }
        foreach (['name', 'description', 'is_active', 'store_id'] as $key) {
            if (array_key_exists($key, $data)) {
                $segment->{$key} = $data[$key];
            }
        }
        $segment->save();
        $this->refreshMembers($segment);

        return $segment->fresh() ?? $segment;
    }

    public function refreshMembers(GrowthSegment $segment): int
    {
        $matches = $this->evaluate($segment)->pluck('id')->all();

        return DB::transaction(function () use ($segment, $matches) {
            GrowthSegmentMember::query()->where('growth_segment_id', $segment->id)->delete();

            $now = now();
            foreach (array_chunk($matches, 200) as $chunk) {
                foreach ($chunk as $profileId) {
                    GrowthSegmentMember::query()->create([
                        'growth_segment_id' => $segment->id,
                        'customer_profile_id' => $profileId,
                        'matched_at' => $now,
                    ]);
                }
            }

            $segment->forceFill([
                'member_count' => count($matches),
                'last_evaluated_at' => $now,
            ])->save();

            return count($matches);
        });
    }

    /**
     * @return Collection<int, CustomerProfile>
     */
    public function evaluate(GrowthSegment $segment): Collection
    {
        $query = CustomerProfile::query()
            ->forCustomers()
            ->with(['metrics', 'tags', 'loyaltyAccount.tier', 'user']);

        if ($segment->store_id) {
            // Prefer customers with orders at this store, or products preference via orders.
            $query->where(function (Builder $q) use ($segment) {
                $q->whereHas('user.orders', fn ($o) => $o->where('store_id', $segment->store_id))
                    ->orWhereDoesntHave('user.orders');
            });
        }

        $rules = $segment->rules['all'] ?? $segment->rules ?? [];
        if (! is_array($rules)) {
            $rules = [];
        }

        return $query->get()->filter(function (CustomerProfile $profile) use ($rules, $segment) {
            foreach ($rules as $rule) {
                if (! $this->matchesRule($profile, $rule, $segment->store_id)) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    public function refreshGrowthStages(): int
    {
        $updated = 0;
        CustomerProfile::query()->forCustomers()->with('metrics')->chunkById(100, function ($profiles) use (&$updated) {
            foreach ($profiles as $profile) {
                $stage = $this->computeStage($profile);
                if ($profile->growth_stage !== $stage) {
                    $profile->forceFill(['growth_stage' => $stage])->save();
                    $updated++;
                }
            }
        });

        return $updated;
    }

    public function computeStage(CustomerProfile $profile): GrowthStage
    {
        $metrics = $profile->metrics;
        $orders = (int) ($metrics?->total_orders ?? 0);
        $spend = (float) ($metrics?->total_spend ?? 0);
        $lastOrder = $metrics?->last_order_at;
        $daysSince = $lastOrder ? $lastOrder->diffInDays(now()) : null;

        if ($orders === 0) {
            return GrowthStage::New;
        }
        if ($daysSince !== null && $daysSince >= 90) {
            return $daysSince >= 120 ? GrowthStage::Winback : GrowthStage::Inactive;
        }
        if ($spend >= 500000 || $orders >= 10) {
            return GrowthStage::Vip;
        }

        return GrowthStage::Active;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function matchesRule(CustomerProfile $profile, array $rule, ?string $storeId): bool
    {
        $field = (string) ($rule['field'] ?? '');
        $op = (string) ($rule['op'] ?? 'eq');
        $value = $rule['value'] ?? null;
        $metrics = $profile->metrics;

        return match ($field) {
            'total_spend' => $this->compare((float) ($metrics?->total_spend ?? 0), $op, (float) $value),
            'total_orders' => $this->compare((int) ($metrics?->total_orders ?? 0), $op, (int) $value),
            'average_order_value' => $this->compare((float) ($metrics?->average_order_value ?? 0), $op, (float) $value),
            'lifecycle_status' => $this->compare(
                $profile->lifecycle_status instanceof \BackedEnum
                    ? $profile->lifecycle_status->value
                    : (string) $profile->lifecycle_status,
                $op,
                (string) $value,
            ),
            'growth_stage' => $this->compare(
                $profile->growth_stage instanceof \BackedEnum
                    ? $profile->growth_stage->value
                    : (string) ($profile->growth_stage ?? ''),
                $op,
                (string) $value,
            ),
            'days_since_last_order' => $this->compare(
                $metrics?->last_order_at ? $metrics->last_order_at->diffInDays(now()) : 99999,
                $op,
                (int) $value,
            ),
            'is_new' => ((bool) $value) === ($metrics === null || (int) ($metrics->total_orders ?? 0) === 0
                || ($profile->created_at && $profile->created_at->gte(now()->subDays((int) config('crm.new_customer_days', 30))))),
            'tag' => $this->matchTags($profile, $op, $value),
            'loyalty_tier' => $this->matchLoyaltyTier($profile, $op, $value),
            'loyalty_points_min' => $this->compare((int) ($profile->loyaltyAccount?->points_balance ?? 0), 'gte', (int) $value),
            'category_id' => $this->purchasedCategory($profile, (string) $value),
            'store_id' => $this->orderedAtStore($profile, (string) ($value ?: $storeId)),
            'marketing_opt_in' => ((bool) $profile->marketing_opt_in) === (bool) $value,
            'not_blocked' => $profile->lifecycle_status !== CustomerLifecycleStatus::Blocked,
            default => true,
        };
    }

    private function matchTags(CustomerProfile $profile, string $op, mixed $value): bool
    {
        $slugs = $profile->tags->pluck('slug')->map(fn ($s) => strtolower((string) $s))->all();
        $wanted = is_array($value) ? $value : [$value];
        $wanted = array_map(fn ($v) => strtolower((string) $v), $wanted);

        if ($op === 'in' || $op === 'eq') {
            return count(array_intersect($slugs, $wanted)) > 0;
        }
        if ($op === 'not_in') {
            return count(array_intersect($slugs, $wanted)) === 0;
        }

        return false;
    }

    private function matchLoyaltyTier(CustomerProfile $profile, string $op, mixed $value): bool
    {
        $code = strtoupper((string) ($profile->loyaltyAccount?->tier?->code ?? ''));
        $wanted = is_array($value) ? $value : [$value];
        $wanted = array_map(fn ($v) => strtoupper((string) $v), $wanted);

        return in_array($code, $wanted, true);
    }

    private function purchasedCategory(CustomerProfile $profile, string $categoryId): bool
    {
        if ($categoryId === '' || $profile->user_id === null) {
            return false;
        }

        return Order::query()
            ->where('user_id', $profile->user_id)
            ->whereHas('items.product', fn ($p) => $p->where('category_id', $categoryId))
            ->exists();
    }

    private function orderedAtStore(CustomerProfile $profile, string $storeId): bool
    {
        if ($storeId === '' || $profile->user_id === null) {
            return false;
        }

        return Order::query()
            ->where('user_id', $profile->user_id)
            ->where('store_id', $storeId)
            ->exists();
    }

    private function compare(float|int|string $actual, string $op, float|int|string $expected): bool
    {
        return match ($op) {
            'eq', '=' => $actual == $expected,
            'neq', '!=' => $actual != $expected,
            'gt', '>' => $actual > $expected,
            'gte', '>=' => $actual >= $expected,
            'lt', '<' => $actual < $expected,
            'lte', '<=' => $actual <= $expected,
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private function assertRules(array $rules): void
    {
        $all = $rules['all'] ?? $rules;
        if (! is_array($all) || $all === []) {
            throw ValidationException::withMessages([
                'rules' => ['Segment rules require a non-empty "all" list.'],
            ]);
        }
        foreach ($all as $i => $rule) {
            if (! is_array($rule) || empty($rule['field']) || empty($rule['op'])) {
                throw ValidationException::withMessages([
                    "rules.all.{$i}" => ['Each rule needs field and op.'],
                ]);
            }
        }
    }
}

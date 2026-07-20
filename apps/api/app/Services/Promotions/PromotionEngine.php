<?php

namespace App\Services\Promotions;

use App\Enums\PromotionRuleType;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Events\Promotions\PromotionActivated;
use App\Events\Promotions\PromotionCreated;
use App\Events\Promotions\PromotionExpired;
use App\Events\Promotions\PromotionUpdated;
use App\Models\Admin;
use App\Models\Promotion;
use App\Models\PromotionRule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PromotionEngine
{
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $this->expireStale();

        $query = Promotion::query()->withCount(['rules', 'usages'])->latest();

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }
        if (filled($filters['type'] ?? null)) {
            $query->where('type', $filters['type']);
        }
        if (filled($filters['search'] ?? null)) {
            $term = '%'.trim((string) $filters['search']).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('code', 'like', $term));
        }

        return $query->paginate($perPage);
    }

    public function show(Promotion $promotion): Promotion
    {
        return $promotion->load(['rules', 'creator:id,name,email'])->loadCount('usages');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?Admin $admin = null): Promotion
    {
        return DB::transaction(function () use ($data, $admin) {
            $promotion = Promotion::query()->create([
                'name' => $data['name'],
                'code' => $this->normalizeCode($data['code'] ?? null, PromotionType::from($data['type'])),
                'type' => $data['type'],
                'discount_type' => $data['discount_type'],
                'value' => $data['value'] ?? 0,
                'currency' => isset($data['currency']) ? strtoupper((string) $data['currency']) : null,
                'status' => $data['status'] ?? PromotionStatus::Draft->value,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'usage_limit' => $data['usage_limit'] ?? null,
                'per_customer_limit' => $data['per_customer_limit'] ?? null,
                'minimum_order_amount' => $data['minimum_order_amount'] ?? null,
                'created_by' => $admin?->id,
            ]);

            $this->syncRules($promotion, $data['rules'] ?? []);

            try {
                event(new PromotionCreated($promotion->fresh(['rules']) ?? $promotion, $admin));
            } catch (\Throwable $e) {
                Log::warning('promotion.created_event_failed', ['message' => $e->getMessage()]);
            }

            return $this->show($promotion);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Promotion $promotion, array $data, ?Admin $admin = null): Promotion
    {
        return DB::transaction(function () use ($promotion, $data, $admin) {
            $before = $promotion->only([
                'name', 'code', 'type', 'discount_type', 'value', 'currency', 'status',
                'starts_at', 'ends_at', 'usage_limit', 'per_customer_limit', 'minimum_order_amount',
            ]);

            $type = isset($data['type'])
                ? PromotionType::from($data['type'])
                : $promotion->type;

            $promotion->fill([
                'name' => $data['name'] ?? $promotion->name,
                'code' => array_key_exists('code', $data)
                    ? $this->normalizeCode($data['code'], $type)
                    : $promotion->code,
                'type' => $data['type'] ?? $promotion->type,
                'discount_type' => $data['discount_type'] ?? $promotion->discount_type,
                'value' => $data['value'] ?? $promotion->value,
                'currency' => array_key_exists('currency', $data)
                    ? ($data['currency'] ? strtoupper((string) $data['currency']) : null)
                    : $promotion->currency,
                'starts_at' => array_key_exists('starts_at', $data) ? $data['starts_at'] : $promotion->starts_at,
                'ends_at' => array_key_exists('ends_at', $data) ? $data['ends_at'] : $promotion->ends_at,
                'usage_limit' => array_key_exists('usage_limit', $data) ? $data['usage_limit'] : $promotion->usage_limit,
                'per_customer_limit' => array_key_exists('per_customer_limit', $data)
                    ? $data['per_customer_limit']
                    : $promotion->per_customer_limit,
                'minimum_order_amount' => array_key_exists('minimum_order_amount', $data)
                    ? $data['minimum_order_amount']
                    : $promotion->minimum_order_amount,
            ]);
            $promotion->save();

            if (array_key_exists('rules', $data)) {
                $this->syncRules($promotion, $data['rules'] ?? []);
            }

            try {
                event(new PromotionUpdated($promotion->fresh(['rules']) ?? $promotion, $before, $admin));
            } catch (\Throwable $e) {
                Log::warning('promotion.updated_event_failed', ['message' => $e->getMessage()]);
            }

            return $this->show($promotion->fresh() ?? $promotion);
        });
    }

    /**
     * @param  array{status: string}  $data
     */
    public function updateStatus(Promotion $promotion, array $data, ?Admin $admin = null): Promotion
    {
        $status = PromotionStatus::from($data['status']);
        $before = $promotion->status;

        if ($status === PromotionStatus::Active && $promotion->ends_at !== null && $promotion->ends_at->isPast()) {
            throw ValidationException::withMessages([
                'status' => ['Cannot activate an expired promotion. Extend ends_at first.'],
            ]);
        }

        $promotion->status = $status;
        $promotion->save();

        if ($status === PromotionStatus::Active && $before !== PromotionStatus::Active) {
            try {
                event(new PromotionActivated($promotion, $admin));
            } catch (\Throwable $e) {
                Log::warning('promotion.activated_event_failed', ['message' => $e->getMessage()]);
            }
        }

        if ($status === PromotionStatus::Expired) {
            try {
                event(new PromotionExpired($promotion, $admin));
            } catch (\Throwable $e) {
                Log::warning('promotion.expired_event_failed', ['message' => $e->getMessage()]);
            }
        }

        try {
            event(new PromotionUpdated(
                $promotion,
                ['status' => $before?->value],
                $admin,
            ));
        } catch (\Throwable $e) {
            Log::warning('promotion.status_updated_event_failed', ['message' => $e->getMessage()]);
        }

        return $this->show($promotion);
    }

    public function expireStale(): int
    {
        $ids = Promotion::query()
            ->where('status', PromotionStatus::Active)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        Promotion::query()->whereIn('id', $ids)->update(['status' => PromotionStatus::Expired->value]);

        foreach ($ids as $id) {
            $promotion = Promotion::query()->find($id);
            if ($promotion) {
                try {
                    event(new PromotionExpired($promotion));
                } catch (\Throwable) {
                    //
                }
            }
        }

        return $ids->count();
    }

    /**
     * @param  list<array{rule_type: string, rule_value: array<string, mixed>}>  $rules
     */
    private function syncRules(Promotion $promotion, array $rules): void
    {
        PromotionRule::query()->where('promotion_id', $promotion->id)->delete();

        foreach ($rules as $rule) {
            $type = PromotionRuleType::from($rule['rule_type']);
            PromotionRule::query()->create([
                'promotion_id' => $promotion->id,
                'rule_type' => $type,
                'rule_value' => $rule['rule_value'] ?? [],
            ]);
        }
    }

    private function normalizeCode(?string $code, PromotionType $type): ?string
    {
        if ($type === PromotionType::Automatic) {
            return $code !== null && trim($code) !== '' ? strtoupper(trim($code)) : null;
        }

        $normalized = strtoupper(trim((string) $code));
        if ($normalized === '') {
            throw ValidationException::withMessages([
                'code' => ['Coupon promotions require a unique code.'],
            ]);
        }

        return preg_replace('/\s+/', '', $normalized) ?: $normalized;
    }
}

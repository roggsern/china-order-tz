<?php

namespace App\Services\Crm;

use App\Enums\CustomerLifecycleStatus;
use App\Enums\CustomerRegistrationSource;
use App\Enums\CustomerTimelineEventType;
use App\Events\Crm\CustomerProfileCreated;
use App\Events\Crm\CustomerProfileUpdated;
use App\Models\Admin;
use App\Models\CustomerMetric;
use App\Models\CustomerProfile;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerProfileService
{
    public function __construct(
        private readonly CustomerCodeGenerator $codes,
        private readonly CustomerMetricsService $metrics,
        private readonly CustomerTimelineService $timeline,
    ) {}

    /**
     * Idempotent: ensure CRM profile + empty metrics for a customer User.
     */
    public function ensureForUser(
        User $user,
        CustomerRegistrationSource $source = CustomerRegistrationSource::SelfRegistration,
        ?Admin $admin = null,
        bool $recordTimeline = true,
    ): CustomerProfile {
        if (! $user->hasRole('customer')) {
            throw new \InvalidArgumentException('CRM profiles are only created for customer role accounts.');
        }

        return DB::transaction(function () use ($user, $source, $admin, $recordTimeline) {
            $existing = CustomerProfile::query()->where('user_id', $user->id)->first();
            if ($existing !== null) {
                $this->metrics->ensure($existing);

                return $existing->loadMissing(['user', 'metrics', 'tags']);
            }

            $profile = CustomerProfile::query()->create([
                'user_id' => $user->id,
                'customer_code' => $this->codes->generate(),
                'registration_source' => $source,
                'lifecycle_status' => CustomerLifecycleStatus::Active,
                'preferred_currency' => 'TZS',
                'marketing_opt_in' => false,
            ]);

            $this->metrics->ensure($profile);

            if ($recordTimeline) {
                $this->timeline->append(
                    $profile,
                    CustomerTimelineEventType::AccountCreated,
                    'Customer account registered',
                    'CRM profile created for '.$profile->customer_code,
                    User::class,
                    $user->id,
                    [
                        'registration_source' => $source->value,
                    ],
                    $user->created_at,
                );
            }

            try {
                event(new CustomerProfileCreated($profile, $admin));
            } catch (\Throwable $e) {
                Log::warning('crm.profile_created_event_failed', [
                    'profile_id' => $profile->id,
                    'message' => $e->getMessage(),
                ]);
            }

            return $profile->load(['user', 'metrics', 'tags']);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateProfile(CustomerProfile $profile, array $attributes, ?Admin $admin = null): CustomerProfile
    {
        $allowed = collect($attributes)->only([
            'preferred_language',
            'preferred_currency',
            'marketing_opt_in',
            'notes_summary',
        ])->all();

        if ($allowed === []) {
            return $profile;
        }

        $before = $profile->only(array_keys($allowed));
        $profile->fill($allowed);
        $profile->save();

        try {
            event(new CustomerProfileUpdated($profile->fresh() ?? $profile, $before, $admin));
        } catch (\Throwable $e) {
            Log::warning('crm.profile_updated_event_failed', [
                'profile_id' => $profile->id,
                'message' => $e->getMessage(),
            ]);
        }

        return $profile->fresh(['user', 'metrics', 'tags']) ?? $profile;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = CustomerProfile::query()
            ->forCustomers()
            ->with([
                'user:id,name,first_name,last_name,email,phone,is_active,created_at',
                'metrics',
                'tags:id,name,slug',
                'loyaltyAccount.tier:id,code,name,earn_multiplier',
            ]);

        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters['sort'] ?? null, $filters['direction'] ?? 'desc');

        return $query->paginate($perPage);
    }

    public function show(CustomerProfile $profile): CustomerProfile
    {
        return $profile->load([
            'user:id,name,first_name,last_name,email,phone,is_active,created_at',
            'metrics',
            'tags:id,name,slug,description',
            'blockedByAdmin:id,name,email',
            'loyaltyAccount.tier:id,code,name,earn_multiplier,benefits',
        ]);
    }

    /**
     * @return array<string, int|string>
     */
    public function summary(): array
    {
        $base = CustomerProfile::query()->forCustomers();

        $today = (clone $base)->whereDate('created_at', today())->count();
        $month = (clone $base)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count();

        $metricsBase = CustomerMetric::query()
            ->whereHas('profile', fn ($q) => $q->forCustomers());

        return [
            'total_customers' => (clone $base)->count(),
            'new_customers_today' => $today,
            'new_customers_this_month' => $month,
            'active_customers' => (clone $base)->where('lifecycle_status', CustomerLifecycleStatus::Active)->count(),
            'dormant_customers' => (clone $base)->where('lifecycle_status', CustomerLifecycleStatus::Dormant)->count(),
            'blocked_customers' => (clone $base)->where('lifecycle_status', CustomerLifecycleStatus::Blocked)->count(),
            'customers_with_purchases' => (clone $metricsBase)->where('total_spend', '>', 0)->count(),
            'customers_with_orders' => (clone $metricsBase)->where('total_orders', '>', 0)->count(),
            'total_lifetime_spend' => number_format((float) (clone $metricsBase)->sum('total_spend'), 2, '.', ''),
            'currency' => 'TZS',
        ];
    }

    /**
     * Idempotent backfill for all customer-role users.
     *
     * @return array{profiles_created: int, metrics_recalculated: int}
     */
    public function backfillExistingCustomers(): array
    {
        $created = 0;
        $recalculated = 0;

        User::query()
            ->whereHas('roles', fn ($q) => $q->where('slug', 'customer'))
            ->orderBy('created_at')
            ->chunkById(100, function ($users) use (&$created, &$recalculated) {
                foreach ($users as $user) {
                    $hadProfile = CustomerProfile::query()->where('user_id', $user->id)->exists();
                    $source = $this->inferRegistrationSource($user);
                    $profile = $this->ensureForUser($user, $source, null, ! $hadProfile);
                    if (! $hadProfile) {
                        $created++;
                    }
                    $this->metrics->recalculate($profile, dispatchEvent: false);
                    $recalculated++;
                }
            });

        return [
            'profiles_created' => $created,
            'metrics_recalculated' => $recalculated,
        ];
    }

    public function inferRegistrationSource(User $user): CustomerRegistrationSource
    {
        // Checkout-created accounts: order placed within minutes of registration.
        $firstOrder = Order::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->first();

        if ($firstOrder !== null && $user->created_at !== null) {
            $delta = abs($firstOrder->created_at->diffInMinutes($user->created_at));
            if ($delta <= 30) {
                return CustomerRegistrationSource::CheckoutRegistration;
            }
        }

        return CustomerRegistrationSource::SelfRegistration;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (filled($filters['search'] ?? null)) {
            $term = trim((string) $filters['search']);
            $like = '%'.$term.'%';
            $query->where(function (Builder $q) use ($like, $term) {
                $q->where('customer_code', 'like', $like)
                    ->orWhereHas('user', function (Builder $uq) use ($like) {
                        $uq->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('phone', 'like', $like)
                            ->orWhere('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like);
                    })
                    ->orWhereHas('user.orders', fn (Builder $oq) => $oq->where('order_number', 'like', $like));

                if (str_starts_with(strtoupper($term), 'CTZ-CUS-') || str_starts_with(strtoupper($term), 'COTZ-')) {
                    $q->orWhere('customer_code', $term);
                }
            });
        }

        if (filled($filters['lifecycle_status'] ?? null)) {
            $query->where('lifecycle_status', $filters['lifecycle_status']);
        }

        if (filled($filters['registration_source'] ?? null)) {
            $query->where('registration_source', $filters['registration_source']);
        }

        if (filled($filters['tag'] ?? null) || filled($filters['tag_id'] ?? null)) {
            $tag = $filters['tag_id'] ?? $filters['tag'];
            $query->whereHas('tags', function (Builder $tq) use ($tag) {
                $tq->where('customer_tags.id', $tag)
                    ->orWhere('customer_tags.slug', $tag);
            });
        }

        if (filled($filters['registered_from'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['registered_from']);
        }
        if (filled($filters['registered_to'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['registered_to']);
        }

        if (filled($filters['last_order_from'] ?? null)) {
            $query->whereHas('metrics', fn (Builder $mq) => $mq->whereDate('last_order_at', '>=', $filters['last_order_from']));
        }
        if (filled($filters['last_order_to'] ?? null)) {
            $query->whereHas('metrics', fn (Builder $mq) => $mq->whereDate('last_order_at', '<=', $filters['last_order_to']));
        }

        if (isset($filters['min_spend']) && $filters['min_spend'] !== '' && $filters['min_spend'] !== null) {
            $query->whereHas('metrics', fn (Builder $mq) => $mq->where('total_spend', '>=', $filters['min_spend']));
        }
        if (isset($filters['max_spend']) && $filters['max_spend'] !== '' && $filters['max_spend'] !== null) {
            $query->whereHas('metrics', fn (Builder $mq) => $mq->where('total_spend', '<=', $filters['max_spend']));
        }

        if (isset($filters['min_orders']) && $filters['min_orders'] !== '' && $filters['min_orders'] !== null) {
            $query->whereHas('metrics', fn (Builder $mq) => $mq->where('total_orders', '>=', (int) $filters['min_orders']));
        }
        if (isset($filters['max_orders']) && $filters['max_orders'] !== '' && $filters['max_orders'] !== null) {
            $query->whereHas('metrics', fn (Builder $mq) => $mq->where('total_orders', '<=', (int) $filters['max_orders']));
        }

        if (filter_var($filters['no_orders'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->where(function (Builder $q) {
                $q->whereDoesntHave('metrics')
                    ->orWhereHas('metrics', fn (Builder $mq) => $mq->where('total_orders', 0));
            });
        }

        if (filter_var($filters['dormant'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->where('lifecycle_status', CustomerLifecycleStatus::Dormant);
        }

        if (filter_var($filters['blocked'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->where('lifecycle_status', CustomerLifecycleStatus::Blocked);
        }
    }

    private function applySort(Builder $query, ?string $sort, string $direction): void
    {
        $dir = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        match ($sort) {
            'spend', 'total_spend' => $query
                ->leftJoin('customer_metrics', 'customer_metrics.customer_profile_id', '=', 'customer_profiles.id')
                ->orderBy('customer_metrics.total_spend', $dir)
                ->select('customer_profiles.*'),
            'orders', 'order_count', 'total_orders' => $query
                ->leftJoin('customer_metrics', 'customer_metrics.customer_profile_id', '=', 'customer_profiles.id')
                ->orderBy('customer_metrics.total_orders', $dir)
                ->select('customer_profiles.*'),
            'last_order', 'last_order_at' => $query
                ->leftJoin('customer_metrics', 'customer_metrics.customer_profile_id', '=', 'customer_profiles.id')
                ->orderBy('customer_metrics.last_order_at', $dir)
                ->select('customer_profiles.*'),
            default => $query->orderBy('customer_profiles.created_at', $dir),
        };
    }
}

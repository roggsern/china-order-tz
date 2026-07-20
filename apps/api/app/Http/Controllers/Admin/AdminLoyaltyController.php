<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LoyaltyEarnRuleType;
use App\Enums\LoyaltyRewardType;
use App\Enums\PromotionDiscountType;
use App\Http\Controllers\Controller;
use App\Http\Resources\LoyaltyAccountResource;
use App\Http\Resources\LoyaltyEarnRuleResource;
use App\Http\Resources\LoyaltyLedgerEntryResource;
use App\Http\Resources\LoyaltyRedemptionResource;
use App\Http\Resources\LoyaltyRewardResource;
use App\Http\Resources\LoyaltyTierResource;
use App\Models\Admin;
use App\Models\CustomerProfile;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyEarnRule;
use App\Models\LoyaltyLedgerEntry;
use App\Models\LoyaltyRedemption;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyTier;
use App\Services\Loyalty\LoyaltyEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class AdminLoyaltyController extends Controller
{
    public function __construct(
        private readonly LoyaltyEngine $loyalty,
    ) {}

    public function dashboard(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->loyalty->dashboard(),
        ]);
    }

    public function customers(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $search = trim((string) $request->query('search', ''));

        $query = LoyaltyAccount::query()
            ->with(['tier:id,code,name,earn_multiplier', 'profile.user:id,name,email,phone'])
            ->latest();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('loyalty_number', 'like', "%{$search}%")
                    ->orWhereHas('profile', function ($p) use ($search) {
                        $p->where('customer_code', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($u) use ($search) {
                                $u->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%");
                            });
                    });
            });
        }

        return LoyaltyAccountResource::collection($query->paginate($perPage));
    }

    public function showCustomer(LoyaltyAccount $account): JsonResponse
    {
        $account->load([
            'tier',
            'profile.user:id,name,email,phone',
            'ledgerEntries' => fn ($q) => $q->latest('created_at')->limit(50),
            'redemptions' => fn ($q) => $q->with('reward')->latest()->limit(20),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'account' => new LoyaltyAccountResource($account),
                'transactions' => LoyaltyLedgerEntryResource::collection($account->ledgerEntries),
                'redemptions' => LoyaltyRedemptionResource::collection($account->redemptions),
            ],
        ]);
    }

    public function enroll(CustomerProfile $customer): JsonResponse
    {
        $account = $this->loyalty->ensureAccount($customer);

        return response()->json([
            'success' => true,
            'message' => 'Loyalty account ready.',
            'data' => new LoyaltyAccountResource($account->load(['tier', 'profile.user'])),
        ]);
    }

    public function adjust(LoyaltyAccount $account, Request $request): JsonResponse
    {
        $data = $request->validate([
            'points' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        /** @var Admin $admin */
        $admin = $request->user();
        $entry = $this->loyalty->adjustPoints($account, (int) $data['points'], $data['reason'], $admin);

        return response()->json([
            'success' => true,
            'message' => 'Points adjusted.',
            'data' => [
                'entry' => new LoyaltyLedgerEntryResource($entry),
                'account' => new LoyaltyAccountResource($account->fresh(['tier', 'profile.user'])),
            ],
        ]);
    }

    public function redeem(LoyaltyAccount $account, Request $request): JsonResponse
    {
        $data = $request->validate([
            'reward_id' => ['required', 'uuid', 'exists:loyalty_rewards,id'],
            'channel' => ['sometimes', 'string', 'in:pos,storefront,admin'],
        ]);

        $reward = LoyaltyReward::query()->findOrFail($data['reward_id']);
        /** @var Admin $admin */
        $admin = $request->user();
        $result = $this->loyalty->redeemReward(
            $account,
            $reward,
            $data['channel'] ?? 'admin',
            $admin,
        );

        return response()->json([
            'success' => true,
            'message' => 'Reward redeemed. Apply promotion code at checkout/POS.',
            'data' => [
                'redemption' => new LoyaltyRedemptionResource($result['redemption']),
                'promotion_code' => $result['promotion_code'],
                'account' => new LoyaltyAccountResource($result['account']),
            ],
        ]);
    }

    public function redemptions(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $rows = LoyaltyRedemption::query()
            ->with(['reward', 'account.profile.user:id,name,email'])
            ->latest()
            ->paginate($perPage);

        return LoyaltyRedemptionResource::collection($rows);
    }

    public function tiers(): AnonymousResourceCollection
    {
        return LoyaltyTierResource::collection(
            LoyaltyTier::query()->orderBy('sort_order')->get()
        );
    }

    public function storeTier(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'unique:loyalty_tiers,code'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'min_lifetime_points' => ['sometimes', 'integer', 'min:0'],
            'min_lifetime_spend' => ['sometimes', 'numeric', 'min:0'],
            'min_orders' => ['sometimes', 'integer', 'min:0'],
            'earn_multiplier' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'benefits' => ['nullable', 'array'],
        ]);

        $tier = LoyaltyTier::query()->create($data);

        return response()->json([
            'success' => true,
            'data' => new LoyaltyTierResource($tier),
        ], 201);
    }

    public function updateTier(LoyaltyTier $tier, Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:64', Rule::unique('loyalty_tiers', 'code')->ignore($tier->id)],
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'min_lifetime_points' => ['sometimes', 'integer', 'min:0'],
            'min_lifetime_spend' => ['sometimes', 'numeric', 'min:0'],
            'min_orders' => ['sometimes', 'integer', 'min:0'],
            'earn_multiplier' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'benefits' => ['nullable', 'array'],
        ]);

        $tier->fill($data)->save();

        return response()->json([
            'success' => true,
            'data' => new LoyaltyTierResource($tier->fresh()),
        ]);
    }

    public function rules(): AnonymousResourceCollection
    {
        return LoyaltyEarnRuleResource::collection(
            LoyaltyEarnRule::query()->orderBy('priority')->get()
        );
    }

    public function storeRule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'unique:loyalty_earn_rules,code'],
            'name' => ['required', 'string', 'max:160'],
            'rule_type' => ['required', Rule::enum(LoyaltyEarnRuleType::class)],
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'spend_amount' => ['nullable', 'numeric', 'min:0'],
            'points_awarded' => ['sometimes', 'integer', 'min:0'],
            'product_id' => ['nullable', 'uuid', 'exists:products,id'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'promotion_id' => ['nullable', 'uuid', 'exists:promotions,id'],
            'bonus_points' => ['nullable', 'integer', 'min:0'],
            'expiry_months' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'config' => ['nullable', 'array'],
        ]);

        $rule = LoyaltyEarnRule::query()->create($data);

        return response()->json([
            'success' => true,
            'data' => new LoyaltyEarnRuleResource($rule),
        ], 201);
    }

    public function updateRule(LoyaltyEarnRule $rule, Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:64', Rule::unique('loyalty_earn_rules', 'code')->ignore($rule->id)],
            'name' => ['sometimes', 'string', 'max:160'],
            'rule_type' => ['sometimes', Rule::enum(LoyaltyEarnRuleType::class)],
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'spend_amount' => ['nullable', 'numeric', 'min:0'],
            'points_awarded' => ['sometimes', 'integer', 'min:0'],
            'product_id' => ['nullable', 'uuid', 'exists:products,id'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'promotion_id' => ['nullable', 'uuid', 'exists:promotions,id'],
            'bonus_points' => ['nullable', 'integer', 'min:0'],
            'expiry_months' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'config' => ['nullable', 'array'],
        ]);

        $rule->fill($data)->save();

        return response()->json([
            'success' => true,
            'data' => new LoyaltyEarnRuleResource($rule->fresh()),
        ]);
    }

    public function rewards(): AnonymousResourceCollection
    {
        return LoyaltyRewardResource::collection(
            LoyaltyReward::query()->orderBy('name')->get()
        );
    }

    public function storeReward(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'unique:loyalty_rewards,code'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'reward_type' => ['required', Rule::enum(LoyaltyRewardType::class)],
            'is_active' => ['sometimes', 'boolean'],
            'points_cost' => ['required', 'integer', 'min:1'],
            'discount_type' => ['nullable', Rule::enum(PromotionDiscountType::class)],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'product_id' => ['nullable', 'uuid', 'exists:products,id'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1'],
            'channels' => ['nullable', 'array'],
            'config' => ['nullable', 'array'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ]);

        $reward = LoyaltyReward::query()->create($data);

        return response()->json([
            'success' => true,
            'data' => new LoyaltyRewardResource($reward),
        ], 201);
    }

    public function updateReward(LoyaltyReward $reward, Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:64', Rule::unique('loyalty_rewards', 'code')->ignore($reward->id)],
            'name' => ['sometimes', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'reward_type' => ['sometimes', Rule::enum(LoyaltyRewardType::class)],
            'is_active' => ['sometimes', 'boolean'],
            'points_cost' => ['sometimes', 'integer', 'min:1'],
            'discount_type' => ['nullable', Rule::enum(PromotionDiscountType::class)],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'product_id' => ['nullable', 'uuid', 'exists:products,id'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1'],
            'channels' => ['nullable', 'array'],
            'config' => ['nullable', 'array'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ]);

        $reward->fill($data)->save();

        return response()->json([
            'success' => true,
            'data' => new LoyaltyRewardResource($reward->fresh()),
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'uuid'],
            'loyalty_number' => ['nullable', 'string'],
            'search' => ['nullable', 'string'],
        ]);

        $account = null;
        if (! empty($data['loyalty_number'])) {
            $account = LoyaltyAccount::query()
                ->with(['tier', 'profile.user:id,name,email,phone'])
                ->where('loyalty_number', $data['loyalty_number'])
                ->first();
        } elseif (! empty($data['customer_id'])) {
            $customerId = $data['customer_id'];
            $profile = CustomerProfile::query()
                ->where(function ($q) use ($customerId) {
                    $q->where('user_id', $customerId)
                        ->orWhere('id', $customerId);
                })
                ->first();
            if ($profile) {
                $account = $this->loyalty->ensureAccount($profile)->load(['tier', 'profile.user']);
            }
        } elseif (! empty($data['search'])) {
            $term = trim($data['search']);
            $account = LoyaltyAccount::query()
                ->with(['tier', 'profile.user:id,name,email,phone'])
                ->where(function ($q) use ($term) {
                    $q->where('loyalty_number', 'like', "%{$term}%")
                        ->orWhereHas('profile.user', function ($u) use ($term) {
                            $u->where('email', 'like', "%{$term}%")
                                ->orWhere('phone', 'like', "%{$term}%")
                                ->orWhere('name', 'like', "%{$term}%");
                        });
                })
                ->first();
        }

        if ($account === null) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No loyalty account found.',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => new LoyaltyAccountResource($account),
        ]);
    }

    public function ledger(LoyaltyAccount $account, Request $request): AnonymousResourceCollection
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

        return LoyaltyLedgerEntryResource::collection(
            LoyaltyLedgerEntry::query()
                ->where('loyalty_account_id', $account->id)
                ->latest('created_at')
                ->paginate($perPage)
        );
    }
}

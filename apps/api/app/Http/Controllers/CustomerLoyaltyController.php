<?php

namespace App\Http\Controllers;

use App\Http\Resources\LoyaltyAccountResource;
use App\Http\Resources\LoyaltyLedgerEntryResource;
use App\Http\Resources\LoyaltyRedemptionResource;
use App\Http\Resources\LoyaltyRewardResource;
use App\Models\LoyaltyLedgerEntry;
use App\Models\LoyaltyRedemption;
use App\Models\LoyaltyReward;
use App\Models\User;
use App\Services\Loyalty\LoyaltyEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerLoyaltyController extends Controller
{
    public function __construct(
        private readonly LoyaltyEngine $loyalty,
    ) {}

    public function profile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $account = $this->loyalty->ensureAccountForUser($user);
        if ($account === null) {
            return response()->json([
                'success' => false,
                'message' => 'Loyalty is available for registered customers only.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new LoyaltyAccountResource($account->load('tier')),
        ]);
    }

    public function transactions(Request $request): AnonymousResourceCollection|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $account = $this->loyalty->ensureAccountForUser($user);
        if ($account === null) {
            return response()->json(['success' => false, 'message' => 'No loyalty account.'], 404);
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        return LoyaltyLedgerEntryResource::collection(
            LoyaltyLedgerEntry::query()
                ->where('loyalty_account_id', $account->id)
                ->latest('created_at')
                ->paginate($perPage)
        );
    }

    public function rewards(Request $request): AnonymousResourceCollection
    {
        return LoyaltyRewardResource::collection(
            LoyaltyReward::query()->activeWindow()->orderBy('points_cost')->get()
        );
    }

    public function redeem(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $account = $this->loyalty->ensureAccountForUser($user);
        if ($account === null) {
            return response()->json(['success' => false, 'message' => 'No loyalty account.'], 404);
        }

        $data = $request->validate([
            'reward_id' => ['required', 'uuid', 'exists:loyalty_rewards,id'],
        ]);

        $reward = LoyaltyReward::query()->findOrFail($data['reward_id']);
        $result = $this->loyalty->redeemReward($account, $reward, 'storefront');

        return response()->json([
            'success' => true,
            'message' => 'Reward redeemed. Use the promotion code at checkout.',
            'data' => [
                'redemption' => new LoyaltyRedemptionResource($result['redemption']),
                'promotion_code' => $result['promotion_code'],
                'account' => new LoyaltyAccountResource($result['account']),
            ],
        ]);
    }

    public function redemptions(Request $request): AnonymousResourceCollection|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $account = $this->loyalty->ensureAccountForUser($user);
        if ($account === null) {
            return response()->json(['success' => false, 'message' => 'No loyalty account.'], 404);
        }

        return LoyaltyRedemptionResource::collection(
            LoyaltyRedemption::query()
                ->where('loyalty_account_id', $account->id)
                ->with('reward')
                ->latest()
                ->paginate(20)
        );
    }
}

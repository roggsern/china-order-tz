<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Crm\CustomerProfileService;
use App\Services\Growth\GrowthEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerGrowthController extends Controller
{
    public function __construct(
        private readonly GrowthEngine $growth,
        private readonly CustomerProfileService $profiles,
    ) {}

    public function offers(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $this->profiles->ensureForUser($user);
        $profile->load('loyaltyAccount.tier');

        return response()->json([
            'success' => true,
            'data' => $this->growth->customerOffers($profile),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $this->profiles->ensureForUser($user);
        $data = $this->growth->customerOffers($profile);

        return response()->json([
            'success' => true,
            'data' => $data['history'],
        ]);
    }
}

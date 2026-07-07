<?php

namespace App\Http\Controllers;

use App\Actions\Profile\ShowDeliveryAddressAction;
use App\Actions\Profile\ShowProfileAction;
use App\Actions\Profile\UpdateDeliveryAddressAction;
use App\Actions\Profile\UpdateProfileAction;
use App\Http\Requests\Profile\UpdateDeliveryAddressRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\DeliveryAddressResource;
use App\Http\Resources\ProfileResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function show(ShowProfileAction $action): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => new ProfileResource($action->handle($user)),
        ]);
    }

    public function update(UpdateProfileRequest $request, UpdateProfileAction $action): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => new ProfileResource($action->handle($user, $request)),
        ]);
    }

    public function showAddress(ShowDeliveryAddressAction $action): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => new DeliveryAddressResource($action->handle($user)),
        ]);
    }

    public function updateAddress(
        UpdateDeliveryAddressRequest $request,
        UpdateDeliveryAddressAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'message' => 'Delivery address updated successfully.',
            'data' => new DeliveryAddressResource($action->handle($user, $request)),
        ]);
    }
}

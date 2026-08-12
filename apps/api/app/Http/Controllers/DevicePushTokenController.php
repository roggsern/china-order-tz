<?php

namespace App\Http\Controllers;

use App\Actions\Devices\DeactivateDevicePushTokenAction;
use App\Actions\Devices\RegisterDevicePushTokenAction;
use App\Http\Requests\Devices\DeactivateDevicePushTokenRequest;
use App\Http\Requests\Devices\RegisterDevicePushTokenRequest;
use App\Http\Resources\DevicePushTokenResource;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class DevicePushTokenController extends Controller
{
    public function store(
        RegisterDevicePushTokenRequest $request,
        RegisterDevicePushTokenAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        $token = $action->handle($user, $request->validatedPayload());

        return ApiResponse::success(
            data: new DevicePushTokenResource($token),
            message: 'Device push token registered',
            status: 201,
        );
    }

    public function destroy(
        DeactivateDevicePushTokenRequest $request,
        DeactivateDevicePushTokenAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        $validated = $request->validated();
        $marked = $action->handle(
            $user,
            isset($validated['installation_id']) ? (string) $validated['installation_id'] : null,
            isset($validated['push_token']) ? (string) $validated['push_token'] : null,
        );

        return ApiResponse::success(
            data: ['deactivated' => $marked],
            message: 'Device push token deactivated',
        );
    }
}

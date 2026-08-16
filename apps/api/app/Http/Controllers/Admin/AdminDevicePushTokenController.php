<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Devices\DeactivateAdminDevicePushTokenAction;
use App\Actions\Devices\RegisterAdminDevicePushTokenAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeactivateAdminDevicePushTokenRequest;
use App\Http\Requests\Admin\RegisterAdminDevicePushTokenRequest;
use App\Http\Resources\DevicePushTokenResource;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;

class AdminDevicePushTokenController extends Controller
{
    public function store(
        RegisterAdminDevicePushTokenRequest $request,
        RegisterAdminDevicePushTokenAction $action,
    ): JsonResponse {
        /** @var Admin $admin */
        $admin = auth()->user();

        $token = $action->handle($admin, $request->validatedPayload());

        return response()->json([
            'success' => true,
            'message' => 'Admin device push token registered',
            'data' => new DevicePushTokenResource($token),
        ], 201);
    }

    public function destroy(
        DeactivateAdminDevicePushTokenRequest $request,
        DeactivateAdminDevicePushTokenAction $action,
    ): JsonResponse {
        /** @var Admin $admin */
        $admin = auth()->user();

        $validated = $request->validated();
        $marked = $action->handle(
            $admin,
            isset($validated['installation_id']) ? (string) $validated['installation_id'] : null,
            isset($validated['push_token']) ? (string) $validated['push_token'] : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Admin device push token deactivated',
            'data' => ['deactivated' => $marked],
        ]);
    }
}

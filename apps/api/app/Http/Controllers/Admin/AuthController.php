<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminAuth\CurrentAdminAction;
use App\Actions\AdminAuth\LoginAdminAction;
use App\Actions\AdminAuth\LogoutAdminAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use App\Http\Resources\AdminResource;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginAdminAction $action): JsonResponse
    {
        $result = $action->handle($request);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'data' => new AdminResource($result['admin']),
        ]);
    }

    public function me(CurrentAdminAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new AdminResource($action->handle()),
        ]);
    }

    public function logout(LogoutAdminAction $action): JsonResponse
    {
        $action->handle();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }
}

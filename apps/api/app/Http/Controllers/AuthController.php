<?php

namespace App\Http\Controllers;

use App\Actions\UserAuth\CurrentUserAction;
use App\Actions\UserAuth\LoginUserAction;
use App\Actions\UserAuth\LogoutUserAction;
use App\Actions\UserAuth\RegisterUserAction;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUserAction $action): JsonResponse
    {
        $result = $action->handle($request);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'data' => new UserResource($result['user']),
        ], 201);
    }

    public function login(LoginRequest $request, LoginUserAction $action): JsonResponse
    {
        $result = $action->handle($request);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'data' => new UserResource($result['user']),
        ]);
    }

    public function me(CurrentUserAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new UserResource($action->handle()),
        ]);
    }

    public function logout(LogoutUserAction $action): JsonResponse
    {
        $action->handle();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }
}

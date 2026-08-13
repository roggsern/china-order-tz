<?php

namespace App\Http\Controllers;

use App\Actions\UserAuth\CurrentUserAction;
use App\Actions\UserAuth\LoginUserAction;
use App\Actions\UserAuth\LogoutUserAction;
use App\Actions\UserAuth\RegisterUserAction;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUserAction $action): JsonResponse
    {
        $result = $action->handle($request);

        return ApiResponse::success(
            data: new UserResource($result['user']),
            message: 'Registration successful',
            status: 201,
            extra: [
                'token' => $result['token'],
                'token_type' => 'Bearer',
            ],
        );
    }

    public function login(LoginRequest $request, LoginUserAction $action): JsonResponse
    {
        $result = $action->handle($request);

        return ApiResponse::success(
            data: new UserResource($result['user']),
            message: 'Login successful',
            extra: [
                'token' => $result['token'],
                'token_type' => 'Bearer',
            ],
        );
    }

    public function me(CurrentUserAction $action): JsonResponse
    {
        return ApiResponse::success(
            data: new UserResource($action->handle()),
        );
    }

    public function logout(LogoutRequest $request, LogoutUserAction $action): JsonResponse
    {
        $action->handle($request->validated());

        return ApiResponse::success(
            data: null,
            message: 'Logged out successfully',
        );
    }
}

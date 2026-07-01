<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminAuth\LoginAdminAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use App\Http\Resources\AdminResource;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginAdminAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => new AdminResource($action->handle($request)),
        ]);
    }
}

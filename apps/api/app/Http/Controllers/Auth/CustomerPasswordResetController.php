<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\CustomerPasswordResetService;
use Illuminate\Http\JsonResponse;

class CustomerPasswordResetController extends Controller
{
    public function __construct(
        private readonly CustomerPasswordResetService $passwordReset,
    ) {}

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $result = $this->passwordReset->requestReset((string) $request->validated('email'));

        return response()->json($result);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $result = $this->passwordReset->resetPassword($request->validated());

        return response()->json($result);
    }
}

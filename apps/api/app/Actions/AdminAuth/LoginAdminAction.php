<?php

namespace App\Actions\AdminAuth;

use App\Http\Requests\Admin\LoginRequest;
use App\Models\Admin;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginAdminAction
{
    public function handle(LoginRequest $request): Admin
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::guard('admin')->attempt($credentials)) {
            $exception = ValidationException::withMessages([
                'email' => ['Invalid credentials'],
            ]);

            $exception->response = response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 422);

            throw $exception;
        }

        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        if (! $admin->is_active) {
            Auth::guard('admin')->logout();

            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Your account has been disabled.',
            ], 403));
        }

        $request->session()->regenerate();

        return $admin->load('role');
    }
}

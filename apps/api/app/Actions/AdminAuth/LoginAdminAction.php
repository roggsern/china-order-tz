<?php

namespace App\Actions\AdminAuth;

use App\Events\Audit\AdminLogin;
use App\Http\Requests\Admin\LoginRequest;
use App\Models\Admin;
use App\Support\Auth\SanctumTokenIssuer;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginAdminAction
{
    /**
     * @return array{admin: Admin, token: string}
     */
    public function handle(LoginRequest $request): array
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

        // Login rotation: one active admin PAT family per account (RC1-G4A).
        SanctumTokenIssuer::revokeAll($admin);

        $token = SanctumTokenIssuer::issueAdmin($admin)->plainTextToken;

        Auth::guard('admin')->logout();

        event(AdminLogin::fromAdmin($admin, $request->ip(), $request->userAgent()));

        return [
            'admin' => $admin->load('role'),
            'token' => $token,
        ];
    }
}

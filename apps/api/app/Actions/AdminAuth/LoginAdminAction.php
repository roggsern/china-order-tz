<?php

namespace App\Actions\AdminAuth;

use App\Http\Requests\Admin\LoginRequest;
use App\Models\Admin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginAdminAction
{
    public function handle(LoginRequest $request): Admin
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::guard('admin')->attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        if (! $admin->is_active) {
            Auth::guard('admin')->logout();

            throw new AuthorizationException('Your admin account has been deactivated.');
        }

        $request->session()->regenerate();

        return $admin->load('role');
    }
}

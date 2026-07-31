<?php

namespace App\Http\Controllers\Storefront\Concerns;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

trait ResolvesOptionalStorefrontCustomer
{
    protected function resolveOptionalStorefrontCustomer(Request $request): ?User
    {
        $authenticated = Auth::guard('sanctum')->user() ?? $request->user();
        if ($authenticated instanceof User) {
            return $authenticated;
        }

        $token = $request->bearerToken();
        if ($token === null || trim($token) === '') {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);
        $user = $accessToken?->tokenable;

        return $user instanceof User ? $user : null;
    }
}

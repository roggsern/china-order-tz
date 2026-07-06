<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = auth('sanctum')->user();

        if (! $admin instanceof Admin) {
            throw new AuthenticationException('Unauthenticated.');
        }

        return $next($request);
    }
}

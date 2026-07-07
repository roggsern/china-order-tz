<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = auth('sanctum')->user();

        if ($admin instanceof Admin && ! $admin->is_active) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Your account has been disabled.',
            ], 403));
        }

        return $next($request);
    }
}

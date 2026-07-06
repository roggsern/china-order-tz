<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('sanctum')->user();

        if ($user instanceof User && ! $user->is_active) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Your account has been disabled.',
            ], 403));
        }

        return $next($request);
    }
}

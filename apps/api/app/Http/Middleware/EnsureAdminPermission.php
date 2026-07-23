<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Support\Admin\AdminPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: admin.permission:orders.mark_paid,orders.cancel
 * Requires ANY of the listed permissions (after auth + admin.active).
 */
class EnsureAdminPermission
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user instanceof Admin) {
            abort(401, 'Unauthenticated.');
        }

        foreach ($permissions as $permission) {
            if (! AdminPermissions::isKnown($permission)) {
                continue;
            }

            if ($user->hasAdminPermission($permission)) {
                return $next($request);
            }
        }

        abort(403, 'This action is unauthorized.');
    }
}

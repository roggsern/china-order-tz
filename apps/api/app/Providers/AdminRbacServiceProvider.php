<?php

namespace App\Providers;

use App\Models\Admin;
use App\Support\Admin\AdminPermissions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AdminRbacServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
         * Intercept only catalogued admin permission abilities (domain.action).
         * Policy method names (view, create, update, …) fall through unchanged
         * so CMS/POS policies keep their existing role/store checks.
         */
        Gate::before(function ($user, string $ability) {
            if (! $user instanceof Admin) {
                return null;
            }

            if (! AdminPermissions::isKnown($ability)) {
                return null;
            }

            return $user->hasAdminPermission($ability);
        });
    }
}

<?php

namespace App\Providers;

use App\Events\Audit\AdminActivatedAudit;
use App\Events\Audit\AdminCreatedAudit;
use App\Events\Audit\AdminDeactivatedAudit;
use App\Events\Audit\AdminRoleAssignedAudit;
use App\Events\Audit\AdminUpdatedAudit;
use App\Events\Audit\RolePermissionsUpdatedAudit;
use App\Listeners\Audit\RecordActivityLog;
use App\Services\Admin\AdminManagementService;
use App\Services\Admin\AdminRoleReadService;
use App\Services\Admin\RolePermissionImpactService;
use App\Services\Admin\RolePermissionManagementService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AdminManagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AdminManagementService::class);
        $this->app->singleton(AdminRoleReadService::class);
        $this->app->singleton(RolePermissionManagementService::class);
        $this->app->singleton(RolePermissionImpactService::class);
    }

    public function boot(): void
    {
        foreach ([
            AdminCreatedAudit::class,
            AdminUpdatedAudit::class,
            AdminRoleAssignedAudit::class,
            AdminActivatedAudit::class,
            AdminDeactivatedAudit::class,
            RolePermissionsUpdatedAudit::class,
        ] as $auditEvent) {
            Event::listen($auditEvent, [RecordActivityLog::class, 'record']);
        }
    }
}

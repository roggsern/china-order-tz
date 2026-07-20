<?php

namespace App\Actions\CMS;

use App\Models\Admin;
use App\Models\CmsNavigationShell;
use App\Services\CMS\CmsNavigationShellService;

class ArchiveCmsNavigationShellAction
{
    public function __construct(private readonly CmsNavigationShellService $shells) {}

    public function handle(CmsNavigationShell $shell, ?Admin $admin = null): CmsNavigationShell
    {
        return $this->shells->archive($shell, $admin);
    }
}

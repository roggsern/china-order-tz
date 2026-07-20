<?php

namespace App\Actions\CMS;

use App\Models\Admin;
use App\Models\CmsNavigationShell;
use App\Services\CMS\CmsNavigationShellService;

class DeleteCmsNavigationShellAction
{
    public function __construct(private readonly CmsNavigationShellService $shells) {}

    public function handle(CmsNavigationShell $shell, ?Admin $admin = null): void
    {
        $this->shells->delete($shell, $admin);
    }
}

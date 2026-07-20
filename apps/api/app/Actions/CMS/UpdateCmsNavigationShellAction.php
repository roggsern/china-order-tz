<?php

namespace App\Actions\CMS;

use App\DTOs\CMS\UpdateCmsNavigationShellData;
use App\Models\Admin;
use App\Models\CmsNavigationShell;
use App\Services\CMS\CmsNavigationShellService;

class UpdateCmsNavigationShellAction
{
    public function __construct(private readonly CmsNavigationShellService $shells) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(CmsNavigationShell $shell, array $payload, ?Admin $admin = null): CmsNavigationShell
    {
        return $this->shells->update($shell, UpdateCmsNavigationShellData::fromArray($payload), $admin);
    }
}

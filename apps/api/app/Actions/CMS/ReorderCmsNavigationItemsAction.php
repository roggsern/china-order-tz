<?php

namespace App\Actions\CMS;

use App\DTOs\CMS\ReorderCmsNavigationItemsData;
use App\Models\Admin;
use App\Models\CmsNavigationShell;
use App\Services\CMS\CmsNavigationShellService;

class ReorderCmsNavigationItemsAction
{
    public function __construct(private readonly CmsNavigationShellService $shells) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(CmsNavigationShell $shell, array $payload, ?Admin $admin = null): CmsNavigationShell
    {
        return $this->shells->reorderItems($shell, ReorderCmsNavigationItemsData::fromArray($payload), $admin);
    }
}

<?php

namespace App\Actions\CMS;

use App\DTOs\CMS\CreateCmsNavigationItemData;
use App\Models\Admin;
use App\Models\CmsNavigationItem;
use App\Models\CmsNavigationShell;
use App\Services\CMS\CmsNavigationShellService;

class CreateCmsNavigationItemAction
{
    public function __construct(private readonly CmsNavigationShellService $shells) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(CmsNavigationShell $shell, array $payload, ?Admin $admin = null): CmsNavigationItem
    {
        return $this->shells->createItem($shell, CreateCmsNavigationItemData::fromArray($payload), $admin);
    }
}

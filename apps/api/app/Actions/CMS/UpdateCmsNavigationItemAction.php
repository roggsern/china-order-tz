<?php

namespace App\Actions\CMS;

use App\DTOs\CMS\UpdateCmsNavigationItemData;
use App\Models\Admin;
use App\Models\CmsNavigationItem;
use App\Models\CmsNavigationShell;
use App\Services\CMS\CmsNavigationShellService;

class UpdateCmsNavigationItemAction
{
    public function __construct(private readonly CmsNavigationShellService $shells) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(
        CmsNavigationShell $shell,
        CmsNavigationItem $item,
        array $payload,
        ?Admin $admin = null,
    ): CmsNavigationItem {
        return $this->shells->updateItem($shell, $item, UpdateCmsNavigationItemData::fromArray($payload), $admin);
    }
}

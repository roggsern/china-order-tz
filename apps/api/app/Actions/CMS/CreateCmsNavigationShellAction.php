<?php

namespace App\Actions\CMS;

use App\DTOs\CMS\CreateCmsNavigationShellData;
use App\Models\Admin;
use App\Models\CmsNavigationShell;
use App\Services\CMS\CmsNavigationShellService;

class CreateCmsNavigationShellAction
{
    public function __construct(private readonly CmsNavigationShellService $shells) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload, ?Admin $admin = null): CmsNavigationShell
    {
        return $this->shells->create(CreateCmsNavigationShellData::fromArray($payload), $admin);
    }
}

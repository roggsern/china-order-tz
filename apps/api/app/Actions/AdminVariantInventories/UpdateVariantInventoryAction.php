<?php

namespace App\Actions\AdminVariantInventories;

use App\Http\Requests\Admin\UpdateVariantInventoryRequest;
use App\Http\Resources\VariantInventoryResource;
use App\Models\Admin;
use App\Models\VariantInventory;
use App\Services\Inventory\AdminInventoryApplicationService;
use Illuminate\Support\Facades\Auth;

class UpdateVariantInventoryAction
{
    public function __construct(
        private readonly AdminInventoryApplicationService $adminInventory,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(UpdateVariantInventoryRequest $request, VariantInventory $inventory): array
    {
        /** @var Admin|null $admin */
        $admin = Auth::user() instanceof Admin ? Auth::user() : null;

        $updated = $this->adminInventory->updateVariantInventory(
            $inventory,
            $request->validated(),
            $admin,
        );

        return (new VariantInventoryResource($updated))->resolve();
    }
}

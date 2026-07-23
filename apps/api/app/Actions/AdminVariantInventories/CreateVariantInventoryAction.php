<?php

namespace App\Actions\AdminVariantInventories;

use App\Http\Requests\Admin\StoreVariantInventoryRequest;
use App\Http\Resources\VariantInventoryResource;
use App\Models\Admin;
use App\Models\ProductVariant;
use App\Services\Inventory\AdminInventoryApplicationService;
use Illuminate\Support\Facades\Auth;

class CreateVariantInventoryAction
{
    public function __construct(
        private readonly AdminInventoryApplicationService $adminInventory,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(StoreVariantInventoryRequest $request, ProductVariant $variant): array
    {
        /** @var Admin|null $admin */
        $admin = Auth::user() instanceof Admin ? Auth::user() : null;

        $inventory = $this->adminInventory->createVariantInventory(
            $variant,
            $request->validated(),
            $admin,
        );

        return (new VariantInventoryResource($inventory))->resolve();
    }
}

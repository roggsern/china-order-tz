<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoreResource;
use App\Models\Admin;
use App\Services\Stores\StoreTeamService;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMyStoresController extends Controller
{
    public function __construct(
        private readonly StoreTeamService $team,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $this->authorize(AdminPermissions::STORES_VIEW);

        $stores = $this->team->myStores($admin)->load(['defaultInventoryLocation', 'terminals']);

        return response()->json([
            'success' => true,
            'data' => StoreResource::collection($stores),
        ]);
    }
}

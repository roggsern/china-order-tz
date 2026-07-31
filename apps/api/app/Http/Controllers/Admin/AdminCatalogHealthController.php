<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Catalog\CatalogHealthService;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;

class AdminCatalogHealthController extends Controller
{
    public function __construct(
        private readonly CatalogHealthService $catalogHealth,
    ) {}

    public function show(): JsonResponse
    {
        $this->authorize(AdminPermissions::CATALOG_VIEW);

        return response()->json([
            'success' => true,
            'data' => $this->catalogHealth->report(),
        ]);
    }
}

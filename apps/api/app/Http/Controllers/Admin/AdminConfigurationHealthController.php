<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ConfigurationHealth\ConfigurationHealthService;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;

class AdminConfigurationHealthController extends Controller
{
    public function __construct(
        private readonly ConfigurationHealthService $configurationHealth,
    ) {}

    public function show(): JsonResponse
    {
        $this->authorize(AdminPermissions::SETTINGS_VIEW);

        return response()->json([
            'success' => true,
            'data' => $this->configurationHealth->report(),
        ]);
    }
}

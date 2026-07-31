<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateFeatureConfigRequest;
use App\Services\Features\FeatureConfigurationService;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;

class AdminFeatureConfigController extends Controller
{
    public function __construct(
        private readonly FeatureConfigurationService $configuration,
    ) {}

    public function show(): JsonResponse
    {
        $this->authorize(AdminPermissions::FEATURES_VIEW);

        return response()->json([
            'success' => true,
            'data' => $this->configuration->getConfig(),
        ]);
    }

    public function update(UpdateFeatureConfigRequest $request): JsonResponse
    {
        $config = $this->configuration->updateConfig(
            $request->all(),
            $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Feature configuration updated successfully.',
            'data' => $config,
        ]);
    }
}

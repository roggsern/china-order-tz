<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateNotificationConfigRequest;
use App\Services\Notifications\NotificationConfigurationService;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;

class AdminNotificationConfigController extends Controller
{
    public function __construct(
        private readonly NotificationConfigurationService $configuration,
    ) {}

    public function show(): JsonResponse
    {
        $this->authorize(AdminPermissions::NOTIFICATIONS_VIEW);

        return response()->json([
            'success' => true,
            'data' => $this->configuration->getConfig(),
        ]);
    }

    public function update(UpdateNotificationConfigRequest $request): JsonResponse
    {
        // Use all() so secret-like keys are rejected before persistence (validated() strips unknowns).
        $config = $this->configuration->updateConfig(
            $request->all(),
            $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Notification configuration updated successfully.',
            'data' => $config,
        ]);
    }
}

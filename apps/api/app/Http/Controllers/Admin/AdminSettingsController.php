<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsGroupRequest;
use App\Services\Settings\SettingsService;
use App\Support\Admin\AdminPermissions;
use App\Support\Settings\SettingsDefinitions;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AdminSettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize(AdminPermissions::SETTINGS_VIEW);

        return response()->json([
            'success' => true,
            'data' => $this->settings->all(),
        ]);
    }

    public function showGroup(string $group): JsonResponse
    {
        $this->authorize(AdminPermissions::SETTINGS_VIEW);

        if (! in_array($group, SettingsDefinitions::groups(), true)) {
            throw ValidationException::withMessages([
                'group' => ["Unknown settings group [{$group}]."],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'group' => $group,
                'settings' => $this->settings->getGroup($group),
            ],
        ]);
    }

    public function updateGroup(string $group, UpdateSettingsGroupRequest $request): JsonResponse
    {
        $updated = $this->settings->updateGroup($group, $request->values(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully.',
            'data' => [
                'group' => $group,
                'settings' => $updated,
            ],
        ]);
    }
}

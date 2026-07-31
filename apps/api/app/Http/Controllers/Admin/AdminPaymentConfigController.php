<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePaymentConfigRequest;
use App\Services\Payments\PaymentConfigurationService;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;

class AdminPaymentConfigController extends Controller
{
    public function __construct(
        private readonly PaymentConfigurationService $configuration,
    ) {}

    public function show(): JsonResponse
    {
        $this->authorize(AdminPermissions::PAYMENTS_CONFIG_VIEW);

        return response()->json([
            'success' => true,
            'data' => $this->configuration->getConfig(),
        ]);
    }

    public function update(UpdatePaymentConfigRequest $request): JsonResponse
    {
        // Use all() so secret-like keys are rejected before persistence (validated() strips unknowns).
        $config = $this->configuration->updateConfig(
            $request->all(),
            $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Payment configuration updated successfully.',
            'data' => $config,
        ]);
    }
}

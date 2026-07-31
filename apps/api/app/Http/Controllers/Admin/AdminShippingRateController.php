<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateShippingRateRequest;
use App\Services\Shipping\ShippingRateService;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;

class AdminShippingRateController extends Controller
{
    public function __construct(
        private readonly ShippingRateService $shippingRates,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize(AdminPermissions::SHIPPING_VIEW);

        return response()->json([
            'success' => true,
            'data' => $this->shippingRates->listRates(),
        ]);
    }

    public function update(string $shippingMethod, UpdateShippingRateRequest $request): JsonResponse
    {
        $method = $this->shippingRates->resolveManagedMethod($shippingMethod);
        $row = $this->shippingRates->updateRate($method, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Shipping rate updated successfully.',
            'data' => $row,
        ]);
    }
}

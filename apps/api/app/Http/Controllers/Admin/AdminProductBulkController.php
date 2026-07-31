<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExecuteProductBulkActionRequest;
use App\Models\Admin;
use App\Services\AdminProducts\ProductBulkActionService;
use Illuminate\Http\JsonResponse;

class AdminProductBulkController extends Controller
{
    public function __construct(
        private readonly ProductBulkActionService $bulkActions,
    ) {}

    public function execute(ExecuteProductBulkActionRequest $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $validated = $request->validated();

        $result = $this->bulkActions->execute(
            $admin,
            (string) $validated['action_key'],
            $validated['product_ids'],
            is_array($validated['payload'] ?? null) ? $validated['payload'] : [],
        );

        return response()->json([
            'success' => true,
            'message' => 'Bulk product action completed.',
            'data' => $result,
        ]);
    }
}

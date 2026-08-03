<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExecuteVariantBulkActionRequest;
use App\Models\Admin;
use App\Models\Product;
use App\Services\AdminProducts\VariantBulkActionService;
use Illuminate\Http\JsonResponse;

class AdminVariantBulkController extends Controller
{
    public function __construct(
        private readonly VariantBulkActionService $bulkActions,
    ) {}

    public function execute(ExecuteVariantBulkActionRequest $request, Product $product): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $validated = $request->validated();

        $result = $this->bulkActions->execute(
            $admin,
            $product,
            (string) $validated['action_key'],
            $validated['variant_ids'],
            is_array($validated['payload'] ?? null) ? $validated['payload'] : [],
        );

        return response()->json([
            'success' => true,
            'message' => 'Bulk variant action completed.',
            'data' => $result,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminStores\GetTaxonomyImportSourceAction;
use App\Actions\AdminStores\ImportTaxonomyToStoreAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportTaxonomyToStoreRequest;
use App\Models\Store;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStoreTaxonomyImportController extends Controller
{
    public function source(
        Request $request,
        Store $store,
        GetTaxonomyImportSourceAction $action,
    ): JsonResponse {
        $this->authorize(AdminPermissions::CATALOG_VIEW);

        $departmentId = $request->query('department_id');
        if (! is_string($departmentId) || trim($departmentId) === '') {
            return response()->json([
                'success' => false,
                'message' => 'department_id is required.',
                'errors' => [
                    'department_id' => ['department_id is required.'],
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $action->handle($store, trim($departmentId)),
        ]);
    }

    public function import(
        ImportTaxonomyToStoreRequest $request,
        Store $store,
        ImportTaxonomyToStoreAction $action,
    ): JsonResponse {
        $validated = $request->validated();

        $result = $action->handle($store, [
            'department_id' => $validated['department_id'],
            'category_ids' => $validated['category_ids'],
            'include_product_types' => $request->boolean('include_product_types', true),
            'include_attribute_mappings' => $request->boolean('include_attribute_mappings', true),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Taxonomy imported into store catalog.',
            'data' => $result,
        ]);
    }
}

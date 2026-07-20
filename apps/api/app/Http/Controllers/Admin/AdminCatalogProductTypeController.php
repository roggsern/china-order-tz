<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminCatalogProductTypes\CreateCatalogProductTypeAction;
use App\Actions\AdminCatalogProductTypes\DeleteCatalogProductTypeAction;
use App\Actions\AdminCatalogProductTypes\GetAdminCatalogProductTypesAction;
use App\Actions\AdminCatalogProductTypes\RestoreCatalogProductTypeAction;
use App\Actions\AdminCatalogProductTypes\ShowCatalogProductTypeAction;
use App\Actions\AdminCatalogProductTypes\UpdateCatalogProductTypeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCatalogProductTypeRequest;
use App\Http\Requests\Admin\UpdateCatalogProductTypeRequest;
use App\Http\Resources\CatalogProductTypeResource;
use App\Models\CatalogProductType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminCatalogProductTypeController extends Controller
{
    public function index(GetAdminCatalogProductTypesAction $action): AnonymousResourceCollection
    {
        return CatalogProductTypeResource::collection($action->handle())
            ->additional(['success' => true]);
    }

    public function store(
        StoreCatalogProductTypeRequest $request,
        CreateCatalogProductTypeAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => new CatalogProductTypeResource($action->handle($request)),
        ], 201);
    }

    public function show(
        CatalogProductType $catalogProductType,
        ShowCatalogProductTypeAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => new CatalogProductTypeResource($action->handle($catalogProductType)),
        ]);
    }

    public function update(
        UpdateCatalogProductTypeRequest $request,
        CatalogProductType $catalogProductType,
        UpdateCatalogProductTypeAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => new CatalogProductTypeResource($action->handle($request, $catalogProductType)),
        ]);
    }

    public function destroy(
        CatalogProductType $catalogProductType,
        DeleteCatalogProductTypeAction $action,
    ): JsonResponse {
        $action->handle($catalogProductType);

        return response()->json([
            'success' => true,
            'message' => 'Product type deleted successfully',
        ]);
    }

    public function restore(string $id, RestoreCatalogProductTypeAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Product type restored successfully.',
            'data' => new CatalogProductTypeResource($action->handle($id)),
        ]);
    }
}

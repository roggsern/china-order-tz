<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminCatalogAttributes\CreateCatalogAttributeAction;
use App\Actions\AdminCatalogAttributes\CreateCatalogAttributeOptionAction;
use App\Actions\AdminCatalogAttributes\DeleteCatalogAttributeAction;
use App\Actions\AdminCatalogAttributes\DeleteCatalogAttributeOptionAction;
use App\Actions\AdminCatalogAttributes\GetAdminCatalogAttributesAction;
use App\Actions\AdminCatalogAttributes\GetCatalogFiltersAction;
use App\Actions\AdminCatalogAttributes\RestoreCatalogAttributeAction;
use App\Actions\AdminCatalogAttributes\ShowCatalogAttributeAction;
use App\Actions\AdminCatalogAttributes\SyncCatalogProductTypeAttributesAction;
use App\Actions\AdminCatalogAttributes\UpdateCatalogAttributeAction;
use App\Actions\AdminCatalogAttributes\UpdateCatalogAttributeOptionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCatalogAttributeOptionRequest;
use App\Http\Requests\Admin\StoreCatalogAttributeRequest;
use App\Http\Requests\Admin\SyncCatalogProductTypeAttributesRequest;
use App\Http\Requests\Admin\UpdateCatalogAttributeOptionRequest;
use App\Http\Requests\Admin\UpdateCatalogAttributeRequest;
use App\Http\Resources\CatalogAttributeOptionResource;
use App\Http\Resources\CatalogAttributeResource;
use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use App\Models\CatalogProductType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminCatalogAttributeController extends Controller
{
    public function index(GetAdminCatalogAttributesAction $action): AnonymousResourceCollection
    {
        return CatalogAttributeResource::collection($action->handle())
            ->additional(['success' => true]);
    }

    public function store(
        StoreCatalogAttributeRequest $request,
        CreateCatalogAttributeAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => new CatalogAttributeResource($action->handle($request)),
        ], 201);
    }

    public function show(
        CatalogAttribute $catalogAttribute,
        ShowCatalogAttributeAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => new CatalogAttributeResource($action->handle($catalogAttribute)),
        ]);
    }

    public function update(
        UpdateCatalogAttributeRequest $request,
        CatalogAttribute $catalogAttribute,
        UpdateCatalogAttributeAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => new CatalogAttributeResource($action->handle($request, $catalogAttribute)),
        ]);
    }

    public function destroy(
        CatalogAttribute $catalogAttribute,
        DeleteCatalogAttributeAction $action,
    ): JsonResponse {
        $action->handle($catalogAttribute);

        return response()->json([
            'success' => true,
            'message' => 'Attribute deleted successfully',
        ]);
    }

    public function restore(string $id, RestoreCatalogAttributeAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Attribute restored successfully.',
            'data' => new CatalogAttributeResource($action->handle($id)),
        ]);
    }

    public function storeOption(
        StoreCatalogAttributeOptionRequest $request,
        CatalogAttribute $catalogAttribute,
        CreateCatalogAttributeOptionAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => new CatalogAttributeOptionResource($action->handle($request, $catalogAttribute)),
        ], 201);
    }

    public function updateOption(
        UpdateCatalogAttributeOptionRequest $request,
        CatalogAttributeOption $catalogAttributeOption,
        UpdateCatalogAttributeOptionAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => new CatalogAttributeOptionResource($action->handle($request, $catalogAttributeOption)),
        ]);
    }

    public function destroyOption(
        CatalogAttributeOption $catalogAttributeOption,
        DeleteCatalogAttributeOptionAction $action,
    ): JsonResponse {
        $action->handle($catalogAttributeOption);

        return response()->json([
            'success' => true,
            'message' => 'Attribute option deleted successfully',
        ]);
    }

    public function syncProductTypeAttributes(
        SyncCatalogProductTypeAttributesRequest $request,
        CatalogProductType $catalogProductType,
        SyncCatalogProductTypeAttributesAction $action,
    ): JsonResponse {
        $type = $action->handle($request, $catalogProductType);

        return response()->json([
            'success' => true,
            'data' => [
                'catalog_product_type' => [
                    'id' => $type->id,
                    'name' => $type->name,
                    'slug' => $type->slug,
                ],
                'attributes' => CatalogAttributeResource::collection($type->attributes),
            ],
        ]);
    }

    public function filters(GetCatalogFiltersAction $action): JsonResponse
    {
        $typeId = request()->query('catalog_product_type_id');
        $typeId = is_string($typeId) ? $typeId : null;

        return response()->json([
            'success' => true,
            'data' => CatalogAttributeResource::collection($action->handle($typeId)),
        ]);
    }
}

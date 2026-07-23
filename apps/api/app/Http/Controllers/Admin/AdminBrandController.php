<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminBrands\CreateBrandAction;
use App\Actions\AdminBrands\DeleteBrandAction;
use App\Actions\AdminBrands\GetAdminBrandsAction;
use App\Actions\AdminBrands\RestoreBrandAction;
use App\Actions\AdminBrands\ShowBrandAction;
use App\Actions\AdminBrands\SyncBrandCategoriesAction;
use App\Actions\AdminBrands\UpdateBrandAction;
use App\Actions\AdminBrands\UploadBrandAssetAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBrandRequest;
use App\Http\Requests\Admin\SyncBrandCategoriesRequest;
use App\Http\Requests\Admin\UpdateBrandRequest;
use App\Http\Requests\Admin\UploadBrandAssetRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminBrandController extends Controller
{
    public function index(GetAdminBrandsAction $action): AnonymousResourceCollection
    {
        $this->authorize(AdminPermissions::CATALOG_VIEW);

        return BrandResource::collection($action->handle())
            ->additional(['success' => true]);
    }

    public function store(StoreBrandRequest $request, CreateBrandAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new BrandResource($action->handle($request)),
        ], 201);
    }

    public function show(Brand $brand, ShowBrandAction $action): JsonResponse
    {
        $this->authorize(AdminPermissions::CATALOG_VIEW);

        return response()->json([
            'success' => true,
            'data' => new BrandResource($action->handle($brand)),
        ]);
    }

    public function update(
        UpdateBrandRequest $request,
        Brand $brand,
        UpdateBrandAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => new BrandResource($action->handle($request, $brand)),
        ]);
    }

    public function destroy(Brand $brand, DeleteBrandAction $action): JsonResponse
    {
        $this->authorize(AdminPermissions::CATALOG_DELETE);

        $action->handle($brand);

        return response()->json([
            'success' => true,
            'message' => 'Brand deleted successfully',
        ]);
    }

    public function restore(string $id, RestoreBrandAction $action): JsonResponse
    {
        $this->authorize(AdminPermissions::CATALOG_RESTORE);

        return response()->json([
            'success' => true,
            'message' => 'Brand restored successfully.',
            'data' => new BrandResource($action->handle($id)),
        ]);
    }

    public function syncCategories(
        SyncBrandCategoriesRequest $request,
        Brand $brand,
        SyncBrandCategoriesAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => new BrandResource($action->handle($request, $brand)),
        ]);
    }

    public function uploadAsset(
        UploadBrandAssetRequest $request,
        Brand $brand,
        UploadBrandAssetAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => new BrandResource($action->handle($request, $brand)),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminSubcategories\CreateSubcategoryAction;
use App\Actions\AdminSubcategories\DeleteSubcategoryAction;
use App\Actions\AdminSubcategories\GetAdminSubcategoriesAction;
use App\Actions\AdminSubcategories\RestoreSubcategoryAction;
use App\Actions\AdminSubcategories\ShowSubcategoryAction;
use App\Actions\AdminSubcategories\UpdateSubcategoryAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubcategoryRequest;
use App\Http\Requests\Admin\UpdateSubcategoryRequest;
use App\Http\Resources\SubcategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminSubcategoryController extends Controller
{
    public function index(GetAdminSubcategoriesAction $action): AnonymousResourceCollection
    {
        return SubcategoryResource::collection($action->handle())
            ->additional(['success' => true]);
    }

    public function store(StoreSubcategoryRequest $request, CreateSubcategoryAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new SubcategoryResource($action->handle($request)),
        ], 201);
    }

    public function show(Category $subcategory, ShowSubcategoryAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new SubcategoryResource($action->handle($subcategory)),
        ]);
    }

    public function update(
        UpdateSubcategoryRequest $request,
        Category $subcategory,
        UpdateSubcategoryAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => new SubcategoryResource($action->handle($request, $subcategory)),
        ]);
    }

    public function destroy(Category $subcategory, DeleteSubcategoryAction $action): JsonResponse
    {
        $action->handle($subcategory);

        return response()->json([
            'success' => true,
            'message' => 'Subcategory deleted successfully',
        ]);
    }

    public function restore(string $id, RestoreSubcategoryAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Subcategory restored successfully.',
            'data' => new SubcategoryResource($action->handle($id)),
        ]);
    }
}

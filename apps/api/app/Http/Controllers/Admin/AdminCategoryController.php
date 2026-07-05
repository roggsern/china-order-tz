<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminCategories\CreateCategoryAction;
use App\Actions\AdminCategories\DeleteCategoryAction;
use App\Actions\AdminCategories\GetAdminCategoriesAction;
use App\Actions\AdminCategories\ShowCategoryAction;
use App\Actions\AdminCategories\UpdateCategoryAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminCategoryController extends Controller
{
    public function index(GetAdminCategoriesAction $action): AnonymousResourceCollection
    {
        return CategoryResource::collection($action->handle())
            ->additional(['success' => true]);
    }

    public function store(StoreCategoryRequest $request, CreateCategoryAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new CategoryResource($action->handle($request)),
        ], 201);
    }

    public function show(Category $category, ShowCategoryAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new CategoryResource($action->handle($category)),
        ]);
    }

    public function update(
        UpdateCategoryRequest $request,
        Category $category,
        UpdateCategoryAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => new CategoryResource($action->handle($request, $category)),
        ]);
    }

    public function destroy(Category $category, DeleteCategoryAction $action): JsonResponse
    {
        $action->handle($category);

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
        ]);
    }
}

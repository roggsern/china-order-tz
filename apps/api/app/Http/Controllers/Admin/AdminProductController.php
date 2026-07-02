<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminProducts\CreateProductAction;
use App\Actions\AdminProducts\GetAdminProductsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Resources\ProductResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminProductController extends Controller
{
    public function index(GetAdminProductsAction $action): AnonymousResourceCollection
    {
        return ProductResource::collection($action->handle())
            ->additional(['success' => true]);
    }

    public function store(StoreProductRequest $request, CreateProductAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new ProductResource($action->handle($request)),
        ], 201);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierProductRequest;
use App\Http\Requests\Admin\StoreSupplierRequest;
use App\Http\Requests\Admin\UpdateSupplierRequest;
use App\Http\Resources\SupplierProductResource;
use App\Http\Resources\SupplierResource;
use App\Models\Admin;
use App\Models\Supplier;
use App\Services\Procurement\SupplierEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminSupplierController extends Controller
{
    public function __construct(
        private readonly SupplierEngine $suppliers,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        return SupplierResource::collection(
            $this->suppliers->paginate($request->only(['search', 'is_active']), $perPage)
        )->additional(['success' => true]);
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $admin = auth('sanctum')->user();
        $supplier = $this->suppliers->create(
            $request->validated(),
            $admin instanceof Admin ? $admin : null,
        );

        return response()->json([
            'success' => true,
            'data' => new SupplierResource($supplier),
        ], 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new SupplierResource($this->suppliers->show($supplier)),
        ]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $admin = auth('sanctum')->user();
        $updated = $this->suppliers->update(
            $supplier,
            $request->validated(),
            $admin instanceof Admin ? $admin : null,
        );

        return response()->json([
            'success' => true,
            'data' => new SupplierResource($updated),
        ]);
    }

    public function storeProduct(StoreSupplierProductRequest $request, Supplier $supplier): JsonResponse
    {
        $mapping = $this->suppliers->upsertSupplierProduct($supplier, $request->validated());

        return response()->json([
            'success' => true,
            'data' => new SupplierProductResource($mapping),
        ], 201);
    }
}

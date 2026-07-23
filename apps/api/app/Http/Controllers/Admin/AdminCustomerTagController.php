<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomerTagRequest;
use App\Http\Requests\Admin\UpdateCustomerTagRequest;
use App\Http\Resources\CustomerTagResource;
use App\Models\CustomerTag;
use App\Services\Crm\CustomerSegmentationService;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCustomerTagController extends Controller
{
    public function __construct(
        private readonly CustomerSegmentationService $segmentation,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::CUSTOMERS_VIEW);

        $activeOnly = filter_var($request->query('active_only', false), FILTER_VALIDATE_BOOLEAN);

        return response()->json([
            'success' => true,
            'data' => CustomerTagResource::collection(
                collect($this->segmentation->listTags($activeOnly)),
            ),
        ]);
    }

    public function store(StoreCustomerTagRequest $request): JsonResponse
    {
        $tag = $this->segmentation->createTag($request->validated());

        return response()->json([
            'success' => true,
            'data' => new CustomerTagResource($tag),
        ], 201);
    }

    public function update(CustomerTag $tag, UpdateCustomerTagRequest $request): JsonResponse
    {
        $updated = $this->segmentation->updateTag($tag, $request->validated());

        return response()->json([
            'success' => true,
            'data' => new CustomerTagResource($updated),
        ]);
    }
}

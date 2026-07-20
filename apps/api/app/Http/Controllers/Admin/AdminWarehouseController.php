<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignWarehousePackerRequest;
use App\Http\Requests\Admin\AssignWarehousePickerRequest;
use App\Http\Requests\Admin\IndexWarehouseJobsRequest;
use App\Http\Requests\Admin\UpdateWarehouseJobStatusRequest;
use App\Http\Resources\WarehouseJobResource;
use App\Models\WarehouseJob;
use App\Services\Warehouse\WarehouseEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminWarehouseController extends Controller
{
    public function __construct(
        private readonly WarehouseEngine $engine,
    ) {}

    public function index(IndexWarehouseJobsRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) ($request->validated('per_page') ?? 20);

        $query = WarehouseJob::query()
            ->with(['order.user', 'fulfillment', 'picker', 'packer'])
            ->latest();

        if ($status = $request->validated('status')) {
            $query->where('status', $status);
        }

        if ($orderId = $request->validated('order_id')) {
            $query->where('order_id', $orderId);
        }

        return WarehouseJobResource::collection($query->paginate($perPage))
            ->additional(['success' => true]);
    }

    public function show(WarehouseJob $job): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new WarehouseJobResource($this->engine->show($job)),
        ]);
    }

    public function updateStatus(
        WarehouseJob $job,
        UpdateWarehouseJobStatusRequest $request,
    ): JsonResponse {
        $updated = $this->engine->updateStatus($job, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Warehouse job status updated.',
            'data' => new WarehouseJobResource($updated),
        ]);
    }

    public function assignPicker(
        WarehouseJob $job,
        AssignWarehousePickerRequest $request,
    ): JsonResponse {
        $validated = $request->validated();
        if (! array_key_exists('picker_id', $validated)) {
            $validated['picker_id'] = $request->user()?->getAuthIdentifier();
        }

        $updated = $this->engine->assignPicker($job, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Picker assigned.',
            'data' => new WarehouseJobResource($updated),
        ]);
    }

    public function assignPacker(
        WarehouseJob $job,
        AssignWarehousePackerRequest $request,
    ): JsonResponse {
        $validated = $request->validated();
        if (! array_key_exists('packer_id', $validated)) {
            $validated['packer_id'] = $request->user()?->getAuthIdentifier();
        }

        $updated = $this->engine->assignPacker($job, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Packer assigned.',
            'data' => new WarehouseJobResource($updated),
        ]);
    }
}

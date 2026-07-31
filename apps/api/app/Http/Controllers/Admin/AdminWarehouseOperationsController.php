<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\WarehousePackingRecordResource;
use App\Http\Resources\WarehousePickListResource;
use App\Http\Resources\WarehouseStockTransferResource;
use App\Models\Admin;
use App\Models\WarehouseBin;
use App\Models\WarehouseFacility;
use App\Models\WarehouseJob;
use App\Models\WarehousePackingLine;
use App\Models\WarehousePackingRecord;
use App\Models\WarehousePickList;
use App\Models\WarehousePickListLine;
use App\Models\WarehouseStockTransfer;
use App\Models\WarehouseZone;
use App\Services\Warehouse\WarehouseLocationService;
use App\Services\Warehouse\WarehousePackingService;
use App\Services\Warehouse\WarehousePickListService;
use App\Services\Warehouse\WarehouseTransferService;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminWarehouseOperationsController extends Controller
{
    public function __construct(
        private readonly WarehousePickListService $pickLists,
        private readonly WarehousePackingService $packing,
        private readonly WarehouseLocationService $locations,
        private readonly WarehouseTransferService $transfers,
    ) {}

    public function indexPickLists(Request $request): AnonymousResourceCollection
    {
        $this->authorizeAnyWarehouseView();

        $query = WarehousePickList::query()
            ->with(['order.user', 'picker', 'warehouseJob', 'lines'])
            ->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return WarehousePickListResource::collection(
            $query->paginate((int) $request->query('per_page', 20)),
        )->additional(['success' => true]);
    }

    public function storePickList(Request $request): JsonResponse
    {
        $this->authorizeWarehouseManage();
        $request->validate(['warehouse_job_id' => ['required', 'uuid', 'exists:warehouse_jobs,id']]);

        $admin = auth('sanctum')->user();
        abort_unless($admin instanceof Admin, 403);

        $job = WarehouseJob::query()->findOrFail($request->input('warehouse_job_id'));
        $pickList = $this->pickLists->createForJob($job, $admin);

        return response()->json([
            'success' => true,
            'message' => 'Pick list created.',
            'data' => new WarehousePickListResource($pickList),
        ], 201);
    }

    public function showPickList(WarehousePickList $pickList): JsonResponse
    {
        $this->authorizeAnyWarehouseView();

        return response()->json([
            'success' => true,
            'data' => new WarehousePickListResource($this->pickLists->show($pickList)),
        ]);
    }

    public function startPickList(WarehousePickList $pickList): JsonResponse
    {
        $this->authorizeWarehouseManage();
        $admin = auth('sanctum')->user();
        abort_unless($admin instanceof Admin, 403);

        return response()->json([
            'success' => true,
            'message' => 'Pick list started.',
            'data' => new WarehousePickListResource($this->pickLists->start($pickList, $admin)),
        ]);
    }

    public function completePickList(WarehousePickList $pickList): JsonResponse
    {
        $this->authorizeWarehouseManage();
        $admin = auth('sanctum')->user();
        abort_unless($admin instanceof Admin, 403);

        return response()->json([
            'success' => true,
            'message' => 'Pick list completed.',
            'data' => new WarehousePickListResource($this->pickLists->complete($pickList, $admin)),
        ]);
    }

    public function updatePickListLine(
        WarehousePickList $pickList,
        WarehousePickListLine $line,
        Request $request,
    ): JsonResponse {
        $this->authorizeWarehouseManage();
        abort_unless($line->pick_list_id === $pickList->id, 404);

        $validated = $request->validate([
            'picked_quantity' => ['required', 'integer', 'min:0'],
        ]);

        $updated = $this->pickLists->updateLine($line, $validated);

        return response()->json([
            'success' => true,
            'data' => $updated,
        ]);
    }

    public function indexPacking(Request $request): AnonymousResourceCollection
    {
        $this->authorizeAnyWarehouseView();

        $query = WarehousePackingRecord::query()
            ->with(['packer', 'warehouseJob.order', 'lines'])
            ->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return WarehousePackingRecordResource::collection(
            $query->paginate((int) $request->query('per_page', 20)),
        )->additional(['success' => true]);
    }

    public function storePacking(Request $request): JsonResponse
    {
        $this->authorizeWarehouseManage();
        $request->validate(['warehouse_job_id' => ['required', 'uuid', 'exists:warehouse_jobs,id']]);

        $admin = auth('sanctum')->user();
        abort_unless($admin instanceof Admin, 403);

        $job = WarehouseJob::query()->findOrFail($request->input('warehouse_job_id'));
        $record = $this->packing->createForJob($job, $admin);

        return response()->json([
            'success' => true,
            'message' => 'Packing record created.',
            'data' => new WarehousePackingRecordResource($record),
        ], 201);
    }

    public function showPacking(WarehousePackingRecord $packing): JsonResponse
    {
        $this->authorizeAnyWarehouseView();

        return response()->json([
            'success' => true,
            'data' => new WarehousePackingRecordResource($this->packing->show($packing)),
        ]);
    }

    public function startPacking(WarehousePackingRecord $packing, Request $request): JsonResponse
    {
        $this->authorizeWarehouseManage();
        $admin = auth('sanctum')->user();
        abort_unless($admin instanceof Admin, 403);

        $notes = $request->input('notes');

        return response()->json([
            'success' => true,
            'message' => 'Packing started.',
            'data' => new WarehousePackingRecordResource(
                $this->packing->start($packing, $admin, is_string($notes) ? $notes : null),
            ),
        ]);
    }

    public function completePacking(WarehousePackingRecord $packing, Request $request): JsonResponse
    {
        $this->authorizeWarehouseManage();
        $admin = auth('sanctum')->user();
        abort_unless($admin instanceof Admin, 403);

        return response()->json([
            'success' => true,
            'message' => 'Packing completed.',
            'data' => new WarehousePackingRecordResource(
                $this->packing->complete(
                    $packing,
                    $admin,
                    $request->input('notes'),
                    $request->input('package_status'),
                ),
            ),
        ]);
    }

    public function updatePackingLine(
        WarehousePackingRecord $packing,
        WarehousePackingLine $line,
        Request $request,
    ): JsonResponse {
        $this->authorizeWarehouseManage();
        abort_unless($line->packing_record_id === $packing->id, 404);

        $validated = $request->validate([
            'packed_quantity' => ['required', 'integer', 'min:0'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->packing->updateLine($line, $validated),
        ]);
    }

    public function indexFacilities(Request $request): JsonResponse
    {
        $this->authorizeAnyWarehouseView();

        return response()->json([
            'success' => true,
            'data' => $this->locations->listFacilities((int) $request->query('per_page', 50)),
        ]);
    }

    public function storeFacility(Request $request): JsonResponse
    {
        $this->authorizeWarehouseManage();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'inventory_warehouse_code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $facility = $this->locations->createFacility($validated);

        return response()->json(['success' => true, 'data' => $facility], 201);
    }

    public function indexZones(Request $request): JsonResponse
    {
        $this->authorizeAnyWarehouseView();

        return response()->json([
            'success' => true,
            'data' => $this->locations->listZones($request->query('facility_id'), (int) $request->query('per_page', 50)),
        ]);
    }

    public function storeZone(Request $request): JsonResponse
    {
        $this->authorizeWarehouseManage();

        $validated = $request->validate([
            'facility_id' => ['required', 'uuid', 'exists:warehouse_facilities,id'],
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->locations->createZone($validated),
        ], 201);
    }

    public function indexBins(Request $request): JsonResponse
    {
        $this->authorizeAnyWarehouseView();

        return response()->json([
            'success' => true,
            'data' => $this->locations->listBins($request->query('zone_id'), (int) $request->query('per_page', 50)),
        ]);
    }

    public function storeBin(Request $request): JsonResponse
    {
        $this->authorizeWarehouseManage();

        $validated = $request->validate([
            'zone_id' => ['required', 'uuid', 'exists:warehouse_zones,id'],
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->locations->createBin($validated),
        ], 201);
    }

    public function indexTransfers(Request $request): AnonymousResourceCollection
    {
        $this->authorizeWarehouseTransferView();

        return WarehouseStockTransferResource::collection(
            $this->transfers->paginate(['status' => $request->query('status')], (int) $request->query('per_page', 20)),
        )->additional(['success' => true]);
    }

    public function storeTransfer(Request $request): JsonResponse
    {
        $this->authorizeWarehouseTransfer();
        $admin = auth('sanctum')->user();
        abort_unless($admin instanceof Admin, 403);

        $validated = $request->validate([
            'from_facility_id' => ['required', 'uuid', 'exists:warehouse_facilities,id'],
            'to_facility_id' => ['required', 'uuid', 'exists:warehouse_facilities,id'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_variant_id' => ['required', 'uuid', 'exists:product_variants,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $transfer = $this->transfers->create($validated, $admin);

        return response()->json([
            'success' => true,
            'message' => 'Transfer requested.',
            'data' => new WarehouseStockTransferResource($transfer),
        ], 201);
    }

    public function showTransfer(WarehouseStockTransfer $transfer): JsonResponse
    {
        $this->authorizeWarehouseTransferView();

        return response()->json([
            'success' => true,
            'data' => new WarehouseStockTransferResource($this->transfers->show($transfer)),
        ]);
    }

    public function approveTransfer(WarehouseStockTransfer $transfer): JsonResponse
    {
        $this->authorizeWarehouseTransfer();
        $admin = auth('sanctum')->user();
        abort_unless($admin instanceof Admin, 403);

        return response()->json([
            'success' => true,
            'data' => new WarehouseStockTransferResource($this->transfers->approve($transfer, $admin)),
        ]);
    }

    public function completeTransfer(WarehouseStockTransfer $transfer): JsonResponse
    {
        $this->authorizeWarehouseTransfer();
        $admin = auth('sanctum')->user();
        abort_unless($admin instanceof Admin, 403);

        return response()->json([
            'success' => true,
            'data' => new WarehouseStockTransferResource($this->transfers->complete($transfer, $admin)),
        ]);
    }

    public function cancelTransfer(WarehouseStockTransfer $transfer): JsonResponse
    {
        $this->authorizeWarehouseTransfer();
        $admin = auth('sanctum')->user();
        abort_unless($admin instanceof Admin, 403);

        return response()->json([
            'success' => true,
            'data' => new WarehouseStockTransferResource($this->transfers->cancel($transfer, $admin)),
        ]);
    }

    private function authorizeAnyWarehouseView(): void
    {
        $admin = auth('sanctum')->user();
        abort_unless($admin instanceof Admin, 403);

        if (! $admin->hasAdminPermission(AdminPermissions::WAREHOUSE_VIEW)
            && ! $admin->hasAdminPermission(AdminPermissions::WAREHOUSE_JOBS_VIEW)
        ) {
            abort(403);
        }
    }

    private function authorizeWarehouseManage(): void
    {
        $admin = auth('sanctum')->user();
        abort_unless($admin instanceof Admin, 403);

        if (! $admin->hasAdminPermission(AdminPermissions::WAREHOUSE_MANAGE)
            && ! $admin->hasAdminPermission(AdminPermissions::WAREHOUSE_JOBS_UPDATE)
        ) {
            abort(403);
        }
    }

    private function authorizeWarehouseTransferView(): void
    {
        $admin = auth('sanctum')->user();
        abort_unless($admin instanceof Admin, 403);

        if (! $admin->hasAdminPermission(AdminPermissions::WAREHOUSE_TRANSFER)
            && ! $admin->hasAdminPermission(AdminPermissions::INVENTORY_TRANSFER)
            && ! $admin->hasAdminPermission(AdminPermissions::WAREHOUSE_VIEW)
        ) {
            abort(403);
        }
    }

    private function authorizeWarehouseTransfer(): void
    {
        $admin = auth('sanctum')->user();
        abort_unless($admin instanceof Admin, 403);

        if (! $admin->hasAdminPermission(AdminPermissions::WAREHOUSE_TRANSFER)
            && ! $admin->hasAdminPermission(AdminPermissions::INVENTORY_TRANSFER)
        ) {
            abort(403);
        }
    }
}

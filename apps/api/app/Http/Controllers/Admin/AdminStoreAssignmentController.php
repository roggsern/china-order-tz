<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StoreAssignmentType;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Store;
use App\Models\StoreUserAssignment;
use App\Services\Stores\StoreAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStoreAssignmentController extends Controller
{
    public function __construct(
        private readonly StoreAssignmentService $assignments,
    ) {}

    public function index(Store $store): JsonResponse
    {
        // Type A — same gate as assignment writes (super-admin only).
        abort_unless(auth('sanctum')->user()?->is_super_admin === true, 403);

        $rows = StoreUserAssignment::query()
            ->with(['admin:id,name,email,role_id', 'admin.role:id,name,slug'])
            ->where('store_id', $store->id)
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function store(Request $request, Store $store): JsonResponse
    {
        /** @var Admin $actor */
        $actor = $request->user();

        $data = $request->validate([
            'admin_id' => ['required', 'uuid', 'exists:admins,id'],
            'assignment_type' => ['sometimes', 'in:permanent,temporary'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $cashier = Admin::query()->findOrFail($data['admin_id']);
        $type = StoreAssignmentType::tryFrom($data['assignment_type'] ?? 'permanent')
            ?? StoreAssignmentType::Permanent;

        $assignment = $this->assignments->assign(
            $cashier,
            $store,
            $actor,
            $type,
            isset($data['starts_at']) ? new \DateTimeImmutable($data['starts_at']) : null,
            isset($data['ends_at']) ? new \DateTimeImmutable($data['ends_at']) : null,
        );

        return response()->json([
            'success' => true,
            'data' => $assignment,
        ], 201);
    }

    public function destroy(Request $request, Store $store, Admin $admin): JsonResponse
    {
        /** @var Admin $actor */
        $actor = $request->user();
        $assignment = $this->assignments->revoke($admin, $store, $actor);

        return response()->json([
            'success' => true,
            'data' => $assignment,
        ]);
    }
}

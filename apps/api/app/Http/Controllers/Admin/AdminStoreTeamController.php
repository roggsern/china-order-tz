<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StoreAssignmentType;
use App\Enums\StoreOperationalScope;
use App\Http\Controllers\Controller;
use App\Http\Resources\StoreUserAssignmentResource;
use App\Models\Admin;
use App\Models\Store;
use App\Services\Stores\StoreTeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminStoreTeamController extends Controller
{
    public function __construct(
        private readonly StoreTeamService $team,
    ) {}

    public function index(Request $request, Store $store): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        abort_unless($this->team->canViewTeam($admin, $store), 403);

        return response()->json([
            'success' => true,
            'data' => StoreUserAssignmentResource::collection($this->team->listTeam($store)),
        ]);
    }

    public function store(Request $request, Store $store): JsonResponse
    {
        /** @var Admin $actor */
        $actor = $request->user();
        abort_unless($this->team->canManageTeam($actor, $store), 403);

        $data = $request->validate([
            'admin_id' => ['required', 'uuid', 'exists:admins,id'],
            'operational_scope' => ['required', Rule::in(StoreOperationalScope::values())],
            'assignment_type' => ['sometimes', Rule::in(['permanent', 'temporary'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $member = Admin::query()->findOrFail($data['admin_id']);
        $scope = StoreOperationalScope::from($data['operational_scope']);
        $type = StoreAssignmentType::tryFrom($data['assignment_type'] ?? 'permanent')
            ?? StoreAssignmentType::Permanent;

        $assignment = $this->team->assign(
            $member,
            $store,
            $actor,
            $scope,
            $type,
            isset($data['starts_at']) ? new \DateTimeImmutable($data['starts_at']) : null,
            isset($data['ends_at']) ? new \DateTimeImmutable($data['ends_at']) : null,
        );

        return response()->json([
            'success' => true,
            'data' => new StoreUserAssignmentResource($assignment),
        ], 201);
    }

    public function update(Request $request, Store $store, Admin $admin): JsonResponse
    {
        /** @var Admin $actor */
        $actor = $request->user();
        abort_unless($this->team->canManageTeam($actor, $store), 403);

        $data = $request->validate([
            'operational_scope' => ['required', Rule::in(StoreOperationalScope::values())],
            'assignment_type' => ['sometimes', Rule::in(['permanent', 'temporary'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $scope = StoreOperationalScope::from($data['operational_scope']);
        $type = StoreAssignmentType::tryFrom($data['assignment_type'] ?? 'permanent')
            ?? StoreAssignmentType::Permanent;

        $assignment = $this->team->update(
            $admin,
            $store,
            $actor,
            $scope,
            $type,
            isset($data['starts_at']) ? new \DateTimeImmutable($data['starts_at']) : null,
            isset($data['ends_at']) ? new \DateTimeImmutable($data['ends_at']) : null,
        );

        return response()->json([
            'success' => true,
            'data' => new StoreUserAssignmentResource($assignment),
        ]);
    }

    public function destroy(Request $request, Store $store, Admin $admin): JsonResponse
    {
        /** @var Admin $actor */
        $actor = $request->user();
        abort_unless($this->team->canManageTeam($actor, $store), 403);

        $assignment = $this->team->remove($admin, $store, $actor);

        return response()->json([
            'success' => true,
            'data' => new StoreUserAssignmentResource($assignment),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexActivityLogsRequest;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminActivityLogController extends Controller
{
    public function index(IndexActivityLogsRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) ($request->validated('per_page') ?? 25);

        $query = ActivityLog::query()->latest('created_at');

        if ($eventType = $request->validated('event_type')) {
            $query->where('event_type', $eventType);
        }

        if ($actorType = $request->validated('actor_type')) {
            $query->where('actor_type', $actorType);
        }

        if ($actorId = $request->validated('actor_id')) {
            $query->where('actor_id', $actorId);
        }

        if ($subjectType = $request->validated('subject_type')) {
            $query->where('subject_type', $subjectType);
        }

        if ($subjectId = $request->validated('subject_id')) {
            $query->where('subject_id', $subjectId);
        }

        if ($search = $request->validated('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', '%'.$search.'%')
                    ->orWhere('action', 'like', '%'.$search.'%')
                    ->orWhere('event_type', 'like', '%'.$search.'%');
            });
        }

        if ($from = $request->validated('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->validated('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        return ActivityLogResource::collection($query->paginate($perPage))
            ->additional(['success' => true]);
    }

    public function show(ActivityLog $activityLog): JsonResponse
    {
        $this->authorize(AdminPermissions::ACTIVITY_LOGS_VIEW);

        return response()->json([
            'success' => true,
            'data' => new ActivityLogResource($activityLog),
        ]);
    }
}

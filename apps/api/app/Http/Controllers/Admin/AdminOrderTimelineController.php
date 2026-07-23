<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TimelineVisibility;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Order;
use App\Services\Tracking\TrackingEngine;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Unified order timeline for staff (internal) or customer preview.
 * Projection-only — never mutates business engines.
 */
class AdminOrderTimelineController extends Controller
{
    public function __construct(
        private readonly TrackingEngine $tracking,
    ) {}

    public function show(Order $order, Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::ORDERS_VIEW);

        $this->admin();

        $view = (string) $request->query('visibility', TimelineVisibility::Internal->value);
        $visibility = TimelineVisibility::tryFrom($view) ?? TimelineVisibility::Internal;

        $payload = $this->tracking->composeOrderTimeline($order, $visibility);

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    public function rebuild(Order $order): JsonResponse
    {
        $this->authorize(AdminPermissions::ORDERS_UPDATE);

        $this->admin();

        $beforeCount = \App\Models\Notification::query()->count();
        $rows = $this->tracking->rebuildOrderProjection($order);
        $afterCount = \App\Models\Notification::query()->count();

        return response()->json([
            'success' => true,
            'message' => 'Order tracking projection rebuilt.',
            'data' => [
                'projected_count' => count($rows),
                'notifications_unchanged' => $beforeCount === $afterCount,
            ],
        ]);
    }

    private function admin(): Admin
    {
        /** @var Admin $admin */
        $admin = auth('sanctum')->user();

        return $admin;
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexAdminNotificationsRequest;
use App\Http\Resources\AdminNotificationResource;
use App\Models\Notification;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminNotificationController extends Controller
{
    public function index(IndexAdminNotificationsRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) ($request->validated('per_page') ?? 20);

        $query = Notification::query()
            ->with(['customer'])
            ->latest();

        if ($channel = $request->validated('channel')) {
            $query->where('channel', $channel);
        }

        if ($status = $request->validated('status')) {
            $query->where('status', $status);
        }

        if ($eventType = $request->validated('event_type')) {
            $query->where('event_type', $eventType);
        }

        if ($customerId = $request->validated('customer_id')) {
            $query->where(function ($q) use ($customerId) {
                $q->where('customer_id', $customerId)
                    ->orWhere('user_id', $customerId);
            });
        }

        return AdminNotificationResource::collection($query->paginate($perPage))
            ->additional(['success' => true]);
    }
}

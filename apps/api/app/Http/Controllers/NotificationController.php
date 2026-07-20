<?php

namespace App\Http\Controllers;

use App\Actions\Notifications\GetUnreadNotificationCountAction;
use App\Actions\Notifications\ListNotificationsAction;
use App\Actions\Notifications\MarkAllNotificationsAsReadAction;
use App\Actions\Notifications\MarkNotificationAsReadAction;
use App\Http\Requests\Notifications\IndexNotificationsRequest;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function index(
        IndexNotificationsRequest $request,
        ListNotificationsAction $action,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = auth()->user();

        return NotificationResource::collection(
            $action->handle($user, (int) $request->validated('per_page', 10))
        )->additional(['success' => true]);
    }

    public function unreadCount(GetUnreadNotificationCountAction $action): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $action->handle($user),
            ],
        ]);
    }

    public function markAsRead(
        Notification $notification,
        MarkNotificationAsReadAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => new NotificationResource($action->handle($notification, $user)),
        ]);
    }

    public function markAllAsRead(MarkAllNotificationsAsReadAction $action): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => [
                'marked' => $action->handle($user),
            ],
        ]);
    }
}

<?php

namespace App\Actions\Notifications;

use App\Models\Notification;
use App\Models\User;
use App\Services\Notifications\NotificationService;

class MarkNotificationAsReadAction
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(Notification $notification, User $user): Notification
    {
        return $this->notificationService->markAsRead($notification, $user);
    }
}

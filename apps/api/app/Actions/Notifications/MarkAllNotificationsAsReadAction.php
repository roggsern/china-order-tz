<?php

namespace App\Actions\Notifications;

use App\Models\User;
use App\Services\Notifications\NotificationService;

class MarkAllNotificationsAsReadAction
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(User $user): int
    {
        return $this->notificationService->markAllAsRead($user);
    }
}

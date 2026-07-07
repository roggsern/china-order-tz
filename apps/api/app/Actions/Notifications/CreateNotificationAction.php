<?php

namespace App\Actions\Notifications;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use App\Services\Notifications\NotificationService;

class CreateNotificationAction
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(
        User $user,
        NotificationType $type,
        string $title,
        string $message,
        array $data = [],
    ): Notification {
        return $this->notificationService->create($user, $type, $title, $message, $data);
    }
}

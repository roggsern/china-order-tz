<?php

namespace App\Actions\Notifications;

use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListNotificationsAction
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(User $user, int $perPage = 10): LengthAwarePaginator
    {
        return $this->notificationService->paginateForUser($user, $perPage);
    }
}

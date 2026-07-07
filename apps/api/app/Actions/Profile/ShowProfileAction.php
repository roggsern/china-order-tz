<?php

namespace App\Actions\Profile;

use App\Models\User;
use App\Services\Profile\ProfileService;

class ShowProfileAction
{
    public function __construct(
        private readonly ProfileService $profileService,
    ) {}

    public function handle(User $user): User
    {
        return $this->profileService->show($user);
    }
}

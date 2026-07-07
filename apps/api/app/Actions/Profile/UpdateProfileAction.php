<?php

namespace App\Actions\Profile;

use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Models\User;
use App\Services\Profile\ProfileService;

class UpdateProfileAction
{
    public function __construct(
        private readonly ProfileService $profileService,
    ) {}

    public function handle(User $user, UpdateProfileRequest $request): User
    {
        return $this->profileService->update($user, $request->validated());
    }
}

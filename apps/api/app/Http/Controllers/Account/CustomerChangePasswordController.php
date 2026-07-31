<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ChangePasswordRequest;
use App\Models\User;
use App\Services\Auth\CustomerChangePasswordService;
use Illuminate\Http\JsonResponse;

class CustomerChangePasswordController extends Controller
{
    public function __construct(
        private readonly CustomerChangePasswordService $changePassword,
    ) {}

    public function __invoke(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $result = $this->changePassword->change($user, $request->validated());

        return response()->json($result);
    }
}

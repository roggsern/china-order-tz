<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ConfirmEmailChangeRequest;
use App\Http\Requests\Account\RequestEmailChangeRequest;
use App\Models\User;
use App\Services\Auth\CustomerEmailChangeService;
use Illuminate\Http\JsonResponse;

class CustomerEmailChangeController extends Controller
{
    public function __construct(
        private readonly CustomerEmailChangeService $emailChange,
    ) {}

    public function request(RequestEmailChangeRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $result = $this->emailChange->request(
            $user,
            (string) $request->validated('new_email'),
            (string) $request->validated('current_password'),
        );

        return response()->json($result);
    }

    public function confirm(ConfirmEmailChangeRequest $request): JsonResponse
    {
        $result = $this->emailChange->confirm((string) $request->validated('token'));

        return response()->json($result);
    }

    public function pending(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $pending = $this->emailChange->pendingFor($user);

        return response()->json([
            'success' => true,
            'data' => $pending === null ? null : [
                'pending_email' => $pending->new_email,
                'expires_at' => $pending->expires_at?->toIso8601String(),
            ],
        ]);
    }
}

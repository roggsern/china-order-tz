<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\CloseAccountRequest;
use App\Models\User;
use App\Services\Auth\CustomerAccountClosureService;
use Illuminate\Http\JsonResponse;

class CustomerAccountClosureController extends Controller
{
    public function __construct(
        private readonly CustomerAccountClosureService $closure,
    ) {}

    public function __invoke(CloseAccountRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $result = $this->closure->close($user, $request->validated());

        return response()->json($result);
    }
}

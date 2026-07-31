<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\CustomerEmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CustomerEmailVerificationController extends Controller
{
    public function __construct(
        private readonly CustomerEmailVerificationService $verification,
    ) {}

    /**
     * Laravel-compatible signed verification endpoint.
     * GET /account/email/verify/{id}/{hash}?expires=&signature=
     */
    public function verify(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::query()->find($id);
        if ($user === null || ! $user->is_active) {
            throw new NotFoundHttpException('User not found.');
        }

        $result = $this->verification->verify($user, $hash);

        return response()->json($result);
    }

    /**
     * SPA confirm: accepts signed query fields and validates against the named verify route.
     * POST /account/email/verify  { id, hash, expires, signature }
     */
    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'uuid'],
            'hash' => ['required', 'string'],
            'expires' => ['required'],
            'signature' => ['required', 'string'],
        ]);

        $absolute = URL::route('verification.verify', [
            'id' => $data['id'],
            'hash' => $data['hash'],
        ], true);

        $check = Request::create($absolute.'?'.http_build_query([
            'expires' => $data['expires'],
            'signature' => $data['signature'],
        ]), 'GET');

        if (! URL::hasValidSignature($check)) {
            throw ValidationException::withMessages([
                'signature' => ['This verification link is invalid or has expired.'],
            ]);
        }

        $user = User::query()->find($data['id']);
        if ($user === null || ! $user->is_active) {
            throw new NotFoundHttpException('User not found.');
        }

        $result = $this->verification->verify($user, $data['hash']);

        return response()->json($result);
    }

    public function resend(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $result = $this->verification->send($user);

        return response()->json($result);
    }
}

<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\ResolvesOptionalStorefrontCustomer;
use App\Http\Requests\Storefront\IdentifyStorefrontVisitorRequest;
use App\Services\Storefront\VisitorIdentityService;
use Illuminate\Http\JsonResponse;

class StorefrontVisitorController extends Controller
{
    use ResolvesOptionalStorefrontCustomer;

    public function identify(
        IdentifyStorefrontVisitorRequest $request,
        VisitorIdentityService $visitors,
    ): JsonResponse {
        $user = $this->resolveOptionalStorefrontCustomer($request);

        $identity = $visitors->identify(
            $request->validated('visitor_uuid'),
            $request->validated('session_id'),
            $user,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'visitor_id' => $identity['visitor_id'],
                'session_id' => $identity['session_id'],
                'visitor_uuid' => $identity['visitor_uuid'],
            ],
        ]);
    }
}

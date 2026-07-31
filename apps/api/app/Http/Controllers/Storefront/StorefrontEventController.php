<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\ResolvesOptionalStorefrontCustomer;
use App\Http\Requests\Storefront\RecordStorefrontEventRequest;
use App\Services\Storefront\StorefrontEventService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class StorefrontEventController extends Controller
{
    use ResolvesOptionalStorefrontCustomer;

    public function store(
        RecordStorefrontEventRequest $request,
        StorefrontEventService $events,
    ): JsonResponse {
        try {
            $event = $events->record($request->validated(), $this->resolveOptionalStorefrontCustomer($request));
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $event->id,
            ],
        ], 201);
    }
}

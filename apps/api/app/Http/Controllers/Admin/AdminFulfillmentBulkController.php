<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExecuteFulfillmentBulkActionRequest;
use App\Models\Admin;
use App\Services\Fulfillment\FulfillmentBulkActionService;
use Illuminate\Http\JsonResponse;

class AdminFulfillmentBulkController extends Controller
{
    public function __construct(
        private readonly FulfillmentBulkActionService $bulkActions,
    ) {}

    public function execute(ExecuteFulfillmentBulkActionRequest $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        $validated = $request->validated();
        $result = $this->bulkActions->execute(
            $admin,
            (string) $validated['action_key'],
            $validated['fulfillment_ids'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Bulk fulfilment action completed.',
            'data' => $result,
        ]);
    }
}

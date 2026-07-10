<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Payments\HandleNmbCallbackAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\WebhookAcknowledgmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NmbWebhookController extends Controller
{
    public function receive(Request $request, HandleNmbCallbackAction $action): JsonResponse
    {
        $payload = $request->all();
        $result = $action->handle(is_array($payload) ? $payload : []);

        return response()->json([
            'success' => true,
            'data' => new WebhookAcknowledgmentResource($result),
        ]);
    }
}

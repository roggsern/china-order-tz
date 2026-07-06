<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Payments\ReceiveNmbWebhookAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\WebhookAcknowledgmentResource;
use Illuminate\Http\JsonResponse;

class NmbWebhookController extends Controller
{
    public function receive(ReceiveNmbWebhookAction $action): JsonResponse
    {
        $result = $action->handle();

        return response()->json([
            'success' => true,
            'data' => new WebhookAcknowledgmentResource($result),
        ], 501);
    }
}

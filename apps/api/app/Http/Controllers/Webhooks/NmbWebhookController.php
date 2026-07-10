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
        $headers = collect($request->headers->all())
            ->mapWithKeys(fn (array $values, string $key) => [$key => $values[0] ?? null])
            ->all();

        $result = $action->handle(
            is_array($payload) ? $payload : [],
            $headers,
            $request->getContent(),
        );

        return response()->json([
            'success' => true,
            'data' => new WebhookAcknowledgmentResource($result),
        ]);
    }
}

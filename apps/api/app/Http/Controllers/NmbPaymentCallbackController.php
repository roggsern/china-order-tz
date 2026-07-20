<?php

namespace App\Http\Controllers;

use App\Services\Payments\Orchestration\NmbOrchestratorCallbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NmbPaymentCallbackController extends Controller
{
    public function __invoke(
        Request $request,
        NmbOrchestratorCallbackService $callbackService,
    ): JsonResponse {
        $payload = $request->all();
        $rawBody = (string) $request->getContent();
        $headers = $request->headers->all();

        $result = $callbackService->handle($payload, $headers, $rawBody);

        return response()->json([
            'success' => true,
            'accepted' => $result['accepted'],
            'message' => $result['message'],
            'transaction_id' => $result['transaction_id'],
        ]);
    }
}

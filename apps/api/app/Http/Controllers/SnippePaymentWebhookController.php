<?php

namespace App\Http\Controllers;

use App\Payments\Gateways\Snippe\SnippeWebhookRetryableException;
use App\Services\Payments\Orchestration\SnippeOrchestratorWebhookService;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SnippePaymentWebhookController extends Controller
{
    public function __invoke(
        SnippeOrchestratorWebhookService $webhookService,
    ): Response {
        $request = request();
        $rawBody = (string) $request->getContent();
        $headers = $request->headers->all();

        try {
            $result = $webhookService->handle($headers, $rawBody);
        } catch (SnippeWebhookRetryableException $exception) {
            return response($exception->getMessage(), $exception->getStatusCode());
        } catch (HttpException $exception) {
            return response($exception->getMessage(), $exception->getStatusCode());
        }

        return response('OK', 200);
    }
}

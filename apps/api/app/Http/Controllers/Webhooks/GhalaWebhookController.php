<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Notifications\WhatsApp\GhalaWebhookProcessor;
use App\Services\Notifications\WhatsApp\GhalaWebhookReplayGuard;
use App\Services\Notifications\WhatsApp\GhalaWebhookSignatureVerifier;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class GhalaWebhookController extends Controller
{
    public function receive(
        GhalaWebhookSignatureVerifier $signatures,
        GhalaWebhookReplayGuard $replay,
        GhalaWebhookProcessor $processor,
    ): Response {
        $request = request();
        $rawBody = (string) $request->getContent();
        $headers = $request->headers->all();

        if (! $signatures->verify($headers, $rawBody)) {
            return response('Unauthorized', 401);
        }

        $deliveryId = trim((string) $request->header('X-Ghala-Delivery', ''));
        if ($deliveryId !== '' && $replay->hasProcessed($deliveryId)) {
            return response('OK', 200);
        }

        $event = trim((string) $request->header('X-Ghala-Event', ''));
        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return response('Invalid payload', 400);
        }

        try {
            $processor->process($event, $payload);
        } catch (Throwable $e) {
            Log::warning('notification.whatsapp.webhook.process_failed', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);

            return response('OK', 200);
        }

        if ($deliveryId !== '') {
            $replay->remember($deliveryId);
        }

        return response('OK', 200);
    }
}

<?php

namespace App\Payments\Gateways\Nmb;

use Illuminate\Support\Facades\Log;

class NmbCheckoutSessionMapper
{
    /**
     * @param  array<string, mixed>  $response
     */
    public function fromResponse(array $response): NmbCheckoutSession
    {
        $result = isset($response['result']) ? (string) $response['result'] : null;
        $session = is_array($response['session'] ?? null) ? $response['session'] : [];
        $sessionId = isset($session['id']) ? (string) $session['id'] : null;
        $successIndicator = isset($response['successIndicator'])
            ? (string) $response['successIndicator']
            : (isset($session['successIndicator']) ? (string) $session['successIndicator'] : null);
        $success = strtoupper($result ?? '') === 'SUCCESS' && filled($sessionId);
        $checkoutUrl = $this->resolveCheckoutUrl($response);

        $message = null;

        if (! $success) {
            $message = (string) (
                $response['error']['explanation']
                ?? $response['error']['cause']
                ?? 'Unable to create NMB checkout session.'
            );
        }

        $mapped = new NmbCheckoutSession(
            success: $success,
            sessionId: $sessionId,
            successIndicator: $successIndicator,
            gatewayReference: $sessionId,
            checkoutUrl: $checkoutUrl,
            result: $result,
            rawResponse: $response,
            message: $message,
        );

        // TEMP diagnostics — no credentials
        Log::info('NMB SESSION MAPPED', [
            'sessionId' => $mapped->sessionId,
            'successIndicator' => $mapped->successIndicator,
        ]);

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function resolveCheckoutUrl(array $response): ?string
    {
        $session = is_array($response['session'] ?? null) ? $response['session'] : [];
        $candidates = [
            $response['checkoutUrl'] ?? null,
            $response['redirectUrl'] ?? null,
            $session['checkoutUrl'] ?? null,
            $session['redirectUrl'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (filled($candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }
}

<?php

namespace App\Payments\Gateways\Nmb;

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
        $successIndicator = isset($session['successIndicator']) ? (string) $session['successIndicator'] : null;
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

        return new NmbCheckoutSession(
            success: $success,
            sessionId: $sessionId,
            successIndicator: $successIndicator,
            gatewayReference: $sessionId,
            checkoutUrl: $checkoutUrl,
            result: $result,
            rawResponse: $response,
            message: $message,
        );
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

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * RC1-G4C3 — assign a safe correlation ID for API requests.
 *
 * Stored on request attributes for exception reporting and shared into log context.
 * Echoed on responses as X-Request-Id for client-side correlation.
 */
class AssignRequestId
{
    public const HEADER = 'X-Request-Id';

    public const ATTRIBUTE = 'request_id';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);

        $request->attributes->set(self::ATTRIBUTE, $requestId);
        $request->headers->set(self::HEADER, $requestId);

        Log::shareContext([
            self::ATTRIBUTE => $requestId,
        ]);

        $response = $next($request);

        if (! $response->headers->has(self::HEADER)) {
            $response->headers->set(self::HEADER, $requestId);
        }

        return $response;
    }

    private function resolveRequestId(Request $request): string
    {
        $incoming = trim((string) $request->headers->get(self::HEADER, ''));

        // Accept only canonical UUIDs from callers (no free-form strings / secrets).
        if ($incoming !== '' && Str::isUuid($incoming)) {
            return Str::lower($incoming);
        }

        return (string) Str::uuid();
    }
}

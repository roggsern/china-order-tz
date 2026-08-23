<?php

namespace App\Http\Middleware;

use App\Services\Features\MaintenanceModeResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks customer/storefront API traffic while features.maintenance_mode is enabled.
 * Admin, health, and payment webhook/callback routes remain available.
 */
class EnforceMaintenanceMode
{
    public function __construct(
        private readonly MaintenanceModeResolver $maintenance,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldBypass($request) || ! $this->maintenance->isEnabled()) {
            return $next($request);
        }

        return response()->json(
            $this->maintenance->blockedResponsePayload(),
            503,
        );
    }

    private function shouldBypass(Request $request): bool
    {
        return $request->is(
            'api/v1/health',
            'api/v1/admin',
            'api/v1/admin/*',
            'api/v1/webhooks/*',
            'api/v1/payments/nmb/callback',
            'api/v1/payments/snippe/webhook',
            'api/v1/storefront/maintenance',
        );
    }
}

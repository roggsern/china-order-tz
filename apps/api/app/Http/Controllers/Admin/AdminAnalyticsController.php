<?php

namespace App\Http\Controllers\Admin;

use App\Events\Audit\AnalyticsReportExportedAudit;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\Analytics\RetailAnalyticsEngine;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAnalyticsController extends Controller
{
    public function __construct(
        private readonly RetailAnalyticsEngine $analytics,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::ANALYTICS_VIEW);

        return $this->jsonSection($request, fn ($filter) => $this->analytics->dashboard($filter));
    }

    public function sales(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::ANALYTICS_VIEW);

        return $this->jsonSection($request, fn ($filter) => $this->analytics->sales($filter));
    }

    public function profit(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::ANALYTICS_VIEW);

        return $this->jsonSection($request, fn ($filter) => $this->analytics->profit($filter));
    }

    public function payments(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::ANALYTICS_VIEW);

        return $this->jsonSection($request, fn ($filter) => $this->analytics->payments($filter));
    }

    public function inventory(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::ANALYTICS_VIEW);

        return $this->jsonSection($request, fn ($filter) => $this->analytics->inventory($filter));
    }

    public function returns(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::ANALYTICS_VIEW);

        return $this->jsonSection($request, fn ($filter) => $this->analytics->returns($filter));
    }

    public function customers(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::ANALYTICS_VIEW);

        return $this->jsonSection($request, fn ($filter) => $this->analytics->customers($filter));
    }

    public function promotions(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::ANALYTICS_VIEW);

        return $this->jsonSection($request, fn ($filter) => $this->analytics->promotions($filter));
    }

    public function loyalty(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::ANALYTICS_VIEW);

        return $this->jsonSection($request, fn ($filter) => $this->analytics->loyalty($filter));
    }

    public function growth(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::ANALYTICS_VIEW);

        return $this->jsonSection($request, fn ($filter) => $this->analytics->growth($filter));
    }

    public function stores(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::ANALYTICS_VIEW);

        return $this->jsonSection($request, fn ($filter) => $this->analytics->stores($filter));
    }

    public function sessions(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::ANALYTICS_VIEW);

        return $this->jsonSection($request, fn ($filter) => $this->analytics->sessions($filter));
    }

    public function export(string $type, Request $request): StreamedResponse|JsonResponse
    {
        $this->authorize(AdminPermissions::ANALYTICS_EXPORT);

        $type = strtolower($type);
        if (! in_array($type, RetailAnalyticsEngine::EXPORT_TYPES, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unknown analytics export type.',
            ], 404);
        }

        /** @var Admin $admin */
        $admin = $request->user();
        $filters = $this->filterInput($request);
        $format = strtolower((string) $request->query('format', 'csv'));

        try {
            $filter = $this->analytics->resolveFilter($admin, $filters);
            $response = $this->analytics->export($type, $format, $filter);

            Log::info('analytics.export', [
                'admin_id' => $admin->id,
                'type' => $type,
                'format' => $format,
                'audited' => $this->analytics->isAuditedExport($type),
            ]);

            if ($this->analytics->isAuditedExport($type)) {
                event(AnalyticsReportExportedAudit::exported($admin, $type, $format, $filters));
            }

            return $response;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * @param  callable(\App\Services\Analytics\DTOs\AnalyticsFilter): array<string, mixed>  $resolver
     */
    private function jsonSection(Request $request, callable $resolver): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        try {
            $filter = $this->analytics->resolveFilter($admin, $this->filterInput($request));
        } catch (ValidationException $e) {
            throw $e;
        }

        return response()->json([
            'success' => true,
            'data' => $resolver($filter),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filterInput(Request $request): array
    {
        return $request->only([
            'from',
            'to',
            'store_id',
            'cashier_id',
            'customer_id',
            'category_id',
            'product_id',
            'payment_method',
            'promotion_id',
            'return_reason_id',
            'pos_only',
        ]);
    }
}

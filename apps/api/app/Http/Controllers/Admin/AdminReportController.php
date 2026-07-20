<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reporting\ReportGenerator;
use App\Services\Reporting\ReportingEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportController extends Controller
{
    public function __construct(
        private readonly ReportingEngine $reporting,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $data = $this->reporting->dashboard($request->only(['from', 'to']));

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function sales(Request $request): JsonResponse
    {
        return $this->reportJson('sales', $request);
    }

    public function orders(Request $request): JsonResponse
    {
        return $this->reportJson('orders', $request);
    }

    public function payments(Request $request): JsonResponse
    {
        return $this->reportJson('payments', $request);
    }

    public function warehouse(Request $request): JsonResponse
    {
        return $this->reportJson('warehouse', $request);
    }

    public function shipments(Request $request): JsonResponse
    {
        return $this->reportJson('shipments', $request);
    }

    public function returns(Request $request): JsonResponse
    {
        return $this->reportJson('returns', $request);
    }

    public function notifications(Request $request): JsonResponse
    {
        return $this->reportJson('notifications', $request);
    }

    public function export(string $type, Request $request): StreamedResponse|JsonResponse
    {
        $type = strtolower($type);
        if (! in_array($type, ReportGenerator::TYPES, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unknown report type.',
            ], 404);
        }

        $format = strtolower((string) $request->query('format', 'csv'));

        try {
            return $this->reporting->export($type, $format, $request->only(['from', 'to']));
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function reportJson(string $type, Request $request): JsonResponse
    {
        $data = $this->reporting->report($type, $request->only(['from', 'to']));

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}

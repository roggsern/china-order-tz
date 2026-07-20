<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfitRecordResource;
use App\Services\CostProfit\ProfitEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProfitController extends Controller
{
    public function __construct(
        private readonly ProfitEngine $profits,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $threshold = (float) ($request->query('low_margin_threshold')
            ?: config('cost_profit.low_margin_threshold', 10));

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $this->profits->dashboard($filters),
                'top_products' => $this->profits->byProducts($filters, 10),
                'low_margin_products' => $this->profits->lowMarginProducts($filters, $threshold, 10),
                'suppliers' => $this->profits->bySuppliers($filters, 10),
                'commerce_channels' => $this->profits->byCommerceChannel($filters),
            ],
        ]);
    }

    public function orders(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        return ProfitRecordResource::collection(
            $this->profits->paginateOrders($this->filters($request), $perPage),
        )->additional(['success' => true])->response();
    }

    public function products(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $limit = min(max((int) $request->query('limit', 20), 1), 100);
        $threshold = (float) ($request->query('low_margin_threshold')
            ?: config('cost_profit.low_margin_threshold', 10));

        return response()->json([
            'success' => true,
            'data' => [
                'top' => $this->profits->byProducts($filters, $limit),
                'low_margin' => $this->profits->lowMarginProducts($filters, $threshold, $limit),
            ],
        ]);
    }

    public function suppliers(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->query('limit', 20), 1), 100);

        return response()->json([
            'success' => true,
            'data' => $this->profits->bySuppliers($this->filters($request), $limit),
        ]);
    }

    /**
     * @return array{from?: string|null, to?: string|null}
     */
    private function filters(Request $request): array
    {
        return [
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ];
    }
}

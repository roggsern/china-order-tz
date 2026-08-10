<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Services\Search\UnifiedMarketplaceSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceSearchController extends Controller
{
    public function __construct(
        private readonly UnifiedMarketplaceSearchService $search,
    ) {}

    public function suggest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:64'],
            'scope' => ['nullable', 'string', 'in:all,china,tz'],
            'limit_products' => ['nullable', 'integer', 'min:1', 'max:24'],
            'limit_brands' => ['nullable', 'integer', 'min:0', 'max:8'],
            'limit_stores' => ['nullable', 'integer', 'min:0', 'max:8'],
            'limit_categories' => ['nullable', 'integer', 'min:0', 'max:8'],
        ]);

        $payload = $this->search->suggest(
            (string) ($validated['q'] ?? ''),
            (string) ($validated['scope'] ?? 'all'),
            [
                'limit_products' => $validated['limit_products'] ?? null,
                'limit_brands' => $validated['limit_brands'] ?? null,
                'limit_stores' => $validated['limit_stores'] ?? null,
                'limit_categories' => $validated['limit_categories'] ?? null,
            ],
        );

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Empty string / null q yields empty results; omit max-length failures for blanks.
            'q' => ['nullable', 'string', 'max:64'],
            'scope' => ['nullable', 'string', 'in:all,china,tz'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
            'sort' => ['nullable', 'string', 'in:relevance,newest'],
        ]);

        $result = $this->search->products(
            (string) ($validated['q'] ?? ''),
            (string) ($validated['scope'] ?? 'all'),
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 24),
            (string) ($validated['sort'] ?? 'relevance'),
        );

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }
}

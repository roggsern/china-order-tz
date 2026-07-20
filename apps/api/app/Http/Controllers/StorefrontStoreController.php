<?php

namespace App\Http\Controllers;

use App\Http\Resources\StoreResource;
use App\Models\Store;
use Illuminate\Http\JsonResponse;

/**
 * Public BUY FROM TZ storefront — browse stores (same Store entity as POS).
 */
class StorefrontStoreController extends Controller
{
    public function index(): JsonResponse
    {
        $stores = Store::query()
            ->storefrontVisible()
            ->orderByRaw('COALESCE(storefront_sort_order, sort_order) asc')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => StoreResource::collection($stores),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $store = Store::query()
            ->storefrontVisible()
            ->where('slug', $slug)
            ->with([
                'categories' => fn ($q) => $q->where('is_active', true)
                    ->whereNull('parent_id')
                    ->with(['children' => fn ($c) => $c->where('is_active', true)->orderBy('sort_order')])
                    ->orderBy('sort_order'),
            ])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => new StoreResource($store),
        ]);
    }
}

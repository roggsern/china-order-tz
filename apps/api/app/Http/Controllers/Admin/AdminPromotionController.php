<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePromotionRequest;
use App\Http\Requests\Admin\UpdatePromotionRequest;
use App\Http\Requests\Admin\UpdatePromotionStatusRequest;
use App\Http\Resources\PromotionResource;
use App\Http\Resources\PromotionUsageResource;
use App\Models\Admin;
use App\Models\Promotion;
use App\Services\Promotions\PromotionEngine;
use App\Services\Promotions\PromotionUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminPromotionController extends Controller
{
    public function __construct(
        private readonly PromotionEngine $promotions,
        private readonly PromotionUsageService $usages,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        return PromotionResource::collection(
            $this->promotions->paginate($request->only(['status', 'type', 'search']), $perPage),
        )->additional(['success' => true]);
    }

    public function store(StorePromotionRequest $request): JsonResponse
    {
        $admin = auth('sanctum')->user();
        $promotion = $this->promotions->create(
            $request->validated(),
            $admin instanceof Admin ? $admin : null,
        );

        return response()->json([
            'success' => true,
            'data' => new PromotionResource($promotion),
        ], 201);
    }

    public function show(Promotion $promotion): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new PromotionResource($this->promotions->show($promotion)),
        ]);
    }

    public function update(Promotion $promotion, UpdatePromotionRequest $request): JsonResponse
    {
        $admin = auth('sanctum')->user();
        $updated = $this->promotions->update(
            $promotion,
            $request->validated(),
            $admin instanceof Admin ? $admin : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Promotion updated.',
            'data' => new PromotionResource($updated),
        ]);
    }

    public function updateStatus(Promotion $promotion, UpdatePromotionStatusRequest $request): JsonResponse
    {
        $admin = auth('sanctum')->user();
        $updated = $this->promotions->updateStatus(
            $promotion,
            $request->validated(),
            $admin instanceof Admin ? $admin : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Promotion status updated.',
            'data' => new PromotionResource($updated),
        ]);
    }

    public function usage(Promotion $promotion, Request $request): AnonymousResourceCollection
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        return PromotionUsageResource::collection(
            $this->usages->paginateForPromotion($promotion, $perPage),
        )->additional(['success' => true]);
    }
}

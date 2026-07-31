<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveAdminReviewRequest;
use App\Http\Requests\Admin\IndexAdminReviewsRequest;
use App\Http\Requests\Admin\RejectAdminReviewRequest;
use App\Http\Requests\Admin\ShowAdminReviewRequest;
use App\Http\Resources\AdminReviewResource;
use App\Models\Admin;
use App\Models\Review;
use App\Services\Reviews\ReviewModerationService;
use Illuminate\Http\JsonResponse;

class AdminReviewController extends Controller
{
    public function __construct(
        private readonly ReviewModerationService $reviews,
    ) {}

    public function index(IndexAdminReviewsRequest $request): JsonResponse
    {
        $perPage = (int) ($request->validated('per_page') ?? 20);
        $paginator = $this->reviews->paginate($request->validated(), $perPage);

        return response()->json([
            'success' => true,
            'data' => AdminReviewResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Review $review, ShowAdminReviewRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new AdminReviewResource($this->reviews->show($review)),
        ]);
    }

    public function approve(Review $review, ApproveAdminReviewRequest $request): JsonResponse
    {
        $admin = auth('sanctum')->user();
        abort_unless($admin instanceof Admin, 403);

        $updated = $this->reviews->approve(
            $review,
            $admin,
            $request->validated('moderation_note'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Review approved.',
            'data' => new AdminReviewResource($updated),
        ]);
    }

    public function reject(Review $review, RejectAdminReviewRequest $request): JsonResponse
    {
        $admin = auth('sanctum')->user();
        abort_unless($admin instanceof Admin, 403);

        $updated = $this->reviews->reject(
            $review,
            $admin,
            $request->validated('moderation_note'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Review rejected.',
            'data' => new AdminReviewResource($updated),
        ]);
    }
}

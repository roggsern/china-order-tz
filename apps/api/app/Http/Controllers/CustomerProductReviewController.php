<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerProductReviewResource;
use App\Models\Product;
use App\Models\User;
use App\Services\Reviews\ProductReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerProductReviewController extends Controller
{
    public function __construct(
        private readonly ProductReviewService $reviews,
    ) {}

    public function index(Product $product): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => CustomerProductReviewResource::collection(
                $this->reviews->listApprovedForProduct($product),
            ),
        ]);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:200'],
            'comment' => ['required', 'string', 'max:5000'],
            'order_id' => ['nullable', 'uuid', 'exists:orders,id'],
        ]);

        $review = $this->reviews->create($user, $product, $data);

        return response()->json([
            'success' => true,
            'message' => 'Review submitted for moderation.',
            'data' => new CustomerProductReviewResource($review->load('user:id,name')),
        ], 201);
    }
}

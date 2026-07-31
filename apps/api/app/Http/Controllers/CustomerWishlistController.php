<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerWishlistItemResource;
use App\Models\Product;
use App\Models\User;
use App\Services\Wishlist\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerWishlistController extends Controller
{
    public function __construct(
        private readonly WishlistService $wishlist,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => CustomerWishlistItemResource::collection($this->wishlist->listFor($user)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'uuid', 'exists:product_variants,id'],
        ]);

        $item = $this->wishlist->add($user, $data['product_id'], $data['product_variant_id'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Added to wishlist.',
            'data' => new CustomerWishlistItemResource($item),
        ], 201);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->wishlist->remove($user, $product->id);

        return response()->json([
            'success' => true,
            'message' => 'Removed from wishlist.',
        ]);
    }
}

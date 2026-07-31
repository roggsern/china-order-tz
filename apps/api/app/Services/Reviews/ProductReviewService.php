<?php

namespace App\Services\Reviews;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\Features\FeatureAvailabilityService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProductReviewService
{
    public function __construct(
        private readonly FeatureAvailabilityService $features,
    ) {}

    /**
     * @return Collection<int, Review>
     */
    public function listApprovedForProduct(Product $product): Collection
    {
        $this->features->assertReviews();

        return Review::query()
            ->with(['user:id,name'])
            ->where('product_id', $product->id)
            ->where('is_approved', true)
            ->orderByDesc('created_at')
            ->get();
    }

    public function create(User $user, Product $product, array $data): Review
    {
        $this->features->assertReviews();

        if (! $product->isPurchasable()) {
            throw ValidationException::withMessages([
                'product_id' => ['Product not found.'],
            ]);
        }

        $existing = Review::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'product_id' => ['You have already reviewed this product.'],
            ]);
        }

        return Review::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'order_id' => $data['order_id'] ?? null,
            'rating' => (int) $data['rating'],
            'title' => $data['title'] ?? null,
            'comment' => $data['comment'] ?? $data['body'] ?? null,
            'body' => $data['body'] ?? $data['comment'] ?? null,
            'is_approved' => false,
            'status' => 'pending',
            'is_verified_purchase' => false,
        ]);
    }
}

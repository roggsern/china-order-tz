<?php

namespace App\Services\Reviews;

use App\Enums\NotificationEventType;
use App\Enums\ReviewStatus;
use App\Events\Audit\ReviewApprovedAudit;
use App\Events\Audit\ReviewRejectedAudit;
use App\Models\Admin;
use App\Models\Review;
use App\Services\Notifications\NotificationPlatform;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ReviewModerationService
{
    public function __construct(
        private readonly NotificationPlatform $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = Review::query()
            ->with([
                'user:id,name,email',
                'product:id,name,slug',
                'moderatedBy:id,name',
            ])
            ->orderByDesc('created_at');

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage);
    }

    public function show(Review $review): Review
    {
        return $review->load([
            'user:id,name,email',
            'product:id,name,slug',
            'order:id,order_number',
            'moderatedBy:id,name',
        ]);
    }

    public function approve(Review $review, Admin $admin, ?string $note = null): Review
    {
        return DB::transaction(function () use ($review, $admin, $note): Review {
            $review = $this->fresh($review);
            $this->assertPending($review);

            $previousStatus = $review->status ?? ReviewStatus::Pending->value;

            $review->fill([
                'is_approved' => true,
                'status' => ReviewStatus::Approved->value,
                'moderation_note' => $note,
                'moderated_by_admin_id' => $admin->id,
                'moderated_at' => now(),
            ])->save();

            $review = $this->show($review);

            event(ReviewApprovedAudit::fromReview($review, $admin, $note, $previousStatus));

            if ($review->user) {
                $this->notifyCustomer(
                    NotificationEventType::ReviewApproved,
                    $review,
                    'review-approved:'.$review->id,
                );
            }

            return $review;
        });
    }

    public function reject(Review $review, Admin $admin, ?string $note = null): Review
    {
        return DB::transaction(function () use ($review, $admin, $note): Review {
            $review = $this->fresh($review);
            $this->assertPending($review);

            $previousStatus = $review->status ?? ReviewStatus::Pending->value;

            $review->fill([
                'is_approved' => false,
                'status' => ReviewStatus::Rejected->value,
                'moderation_note' => $note,
                'moderated_by_admin_id' => $admin->id,
                'moderated_at' => now(),
            ])->save();

            $review = $this->show($review);

            event(ReviewRejectedAudit::fromReview($review, $admin, $note, $previousStatus));

            if ($review->user) {
                $this->notifyCustomer(
                    NotificationEventType::ReviewRejected,
                    $review,
                    'review-rejected:'.$review->id,
                );
            }

            return $review;
        });
    }

    /**
     * @param  Builder<Review>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $status = $filters['status'] ?? null;

        if (is_string($status) && $status !== '' && $status !== 'all') {
            if ($status === ReviewStatus::Pending->value) {
                $query->where(function (Builder $builder): void {
                    $builder
                        ->where('status', ReviewStatus::Pending->value)
                        ->orWhere(function (Builder $nested): void {
                            $nested->whereNull('status')->where('is_approved', false);
                        });
                });
            } else {
                $query->where('status', $status);
            }
        }

        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (! empty($filters['customer_id'])) {
            $query->where('user_id', $filters['customer_id']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.trim((string) $filters['search']).'%';
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('title', 'like', $search)
                    ->orWhere('comment', 'like', $search)
                    ->orWhere('body', 'like', $search)
                    ->orWhereHas('product', fn (Builder $product) => $product->where('name', 'like', $search))
                    ->orWhereHas('user', fn (Builder $user) => $user
                        ->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search));
            });
        }
    }

    private function assertPending(Review $review): void
    {
        $status = $review->status ?? ReviewStatus::Pending->value;

        if ($status !== ReviewStatus::Pending->value) {
            throw ValidationException::withMessages([
                'review' => ['Only pending reviews can be moderated.'],
            ]);
        }
    }

    private function fresh(Review $review): Review
    {
        return Review::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
    }

    private function notifyCustomer(NotificationEventType $type, Review $review, string $idempotencyKey): void
    {
        $customer = $review->user;

        if ($customer === null) {
            return;
        }

        try {
            $this->notifications->notifyCustomer(
                $type,
                $customer,
                [
                    'customer_name' => $customer->name,
                    'product_name' => $review->product?->name,
                    'product_id' => $review->product_id,
                    'product_slug' => $review->product?->slug,
                    'review_id' => $review->id,
                    'rating' => (string) $review->rating,
                    'moderation_note' => $review->moderation_note,
                ],
                idempotencyKey: $idempotencyKey,
            );
        } catch (\Throwable $e) {
            Log::warning('reviews.notify_customer_failed', [
                'review_id' => $review->id,
                'type' => $type->value,
                'message' => $e->getMessage(),
            ]);
        }
    }
}

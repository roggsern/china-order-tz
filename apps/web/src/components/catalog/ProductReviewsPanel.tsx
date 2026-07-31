"use client";

import { useCallback, useEffect, useState } from "react";
import type { CustomerReview } from "@/lib/types/catalog";
import { usePathname } from "next/navigation";
import { useFeatureAvailability } from "@/hooks/use-feature-availability";
import { useCustomerSession } from "@/lib/customer/use-customer-session";
import { fetchProductReviews } from "@/lib/api/customer-reviews";
import { AuthInvitationCard } from "@/components/auth/AuthInvitationCard";
import { ProductReviewForm } from "./ProductReviewForm";
import { RatingStars } from "./RatingStars";

interface ProductReviewsPanelProps {
  productSlug: string;
  reviews: CustomerReview[];
  reviewCount: number;
  averageRating: number;
  compact?: boolean;
}

function ratingDistribution(reviews: CustomerReview[]) {
  const buckets = [5, 4, 3, 2, 1].map((stars) => ({
    stars,
    count: reviews.filter((review) => Math.round(review.rating) === stars).length,
  }));
  const total = Math.max(reviews.length, 1);
  return buckets.map((bucket) => ({
    ...bucket,
    percent: Math.round((bucket.count / total) * 100),
  }));
}

export function ProductReviewsPanel({
  productSlug,
  reviews: initialReviews,
  reviewCount,
  averageRating,
  compact = false,
}: ProductReviewsPanelProps) {
  const [reviews, setReviews] = useState(initialReviews);
  const [isLoadingReviews, setIsLoadingReviews] = useState(false);
  const distribution = ratingDistribution(reviews);
  const { isLoggedIn, isReady } = useCustomerSession();
  const { reviews: reviewsEnabled, isReady: featuresReady } = useFeatureAvailability();
  const pathname = usePathname();

  const loadReviews = useCallback(async () => {
    if (!productSlug.trim()) {
      return;
    }

    setIsLoadingReviews(true);

    try {
      const nextReviews = await fetchProductReviews(productSlug);
      setReviews(nextReviews);
    } catch {
      setReviews(initialReviews);
    } finally {
      setIsLoadingReviews(false);
    }
  }, [initialReviews, productSlug]);

  useEffect(() => {
    if (!featuresReady || !reviewsEnabled) {
      return;
    }

    void loadReviews();
  }, [featuresReady, reviewsEnabled, loadReviews]);

  if (featuresReady && !reviewsEnabled) {
    return null;
  }

  return (
    <div className={compact ? "space-y-4" : "max-w-3xl space-y-8"}>
      <div
        className={`grid gap-5 rounded-2xl border border-zinc-100 bg-gradient-to-br from-zinc-50 via-white to-[#c9a227]/5 ${
          compact ? "p-4" : "p-6 sm:grid-cols-[auto_1fr] sm:items-center"
        }`}
      >
        <div className="text-center sm:text-left">
          <p className={`font-bold tracking-tight text-zinc-900 ${compact ? "text-3xl" : "text-5xl"}`}>
            {averageRating.toFixed(1)}
          </p>
          <div className="mt-2 flex justify-center sm:justify-start">
            <RatingStars rating={averageRating} size={compact ? "md" : "lg"} />
          </div>
          <p className="mt-2 text-sm text-zinc-500">
            Based on {reviewCount.toLocaleString()} verified reviews
          </p>
        </div>

        <div className="space-y-2">
          {distribution.map((bucket) => (
            <div key={bucket.stars} className="flex items-center gap-2.5 text-xs text-zinc-600">
              <span className="w-8 tabular-nums">{bucket.stars}★</span>
              <div className="h-2 flex-1 overflow-hidden rounded-full bg-zinc-100">
                <div
                  className="h-full rounded-full bg-[#c9a227] transition-all duration-500"
                  style={{ width: `${bucket.percent}%` }}
                />
              </div>
              <span className="w-10 text-right tabular-nums text-zinc-400">{bucket.percent}%</span>
            </div>
          ))}
        </div>
      </div>

      {isReady && !isLoggedIn ? (
        <AuthInvitationCard
          context="reviews"
          returnUrl={pathname || "/products"}
          compact
        />
      ) : isReady && isLoggedIn ? (
        <ProductReviewForm
          productSlug={productSlug}
          compact={compact}
          onSubmitted={loadReviews}
        />
      ) : null}

      <div className={compact ? "space-y-3" : "space-y-4"}>
        {isLoadingReviews && reviews.length === 0 ? (
          <div className="rounded-2xl border border-zinc-100 bg-zinc-50/80 px-4 py-8 text-center text-sm text-zinc-500">
            Loading reviews...
          </div>
        ) : reviews.length === 0 ? (
          <div className="rounded-2xl border border-dashed border-zinc-200 bg-zinc-50/80 px-4 py-8 text-center">
            <span className="text-2xl" aria-hidden>
              ⭐
            </span>
            <p className="mt-3 text-sm font-bold text-zinc-900">No reviews yet</p>
            <p className="mt-1 text-sm text-zinc-500">
              Be the first to share your experience.
            </p>
          </div>
        ) : (
          reviews.map((review) => (
            <article
              key={review.id}
              className={`rounded-2xl border border-zinc-100 bg-white transition hover:border-[#c9a227]/20 hover:shadow-[0_8px_24px_rgba(0,0,0,0.04)] ${
                compact ? "p-3.5" : "p-5"
              }`}
            >
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex items-center gap-3">
                  <span className="flex h-10 w-10 items-center justify-center rounded-full bg-[#c9a227]/15 text-sm font-bold text-[#8b6914]">
                    {review.author.slice(0, 1).toUpperCase()}
                  </span>
                  <div>
                    <p className="font-semibold text-zinc-900">{review.author}</p>
                    <p className="text-xs text-zinc-400">{review.date}</p>
                  </div>
                </div>
                <RatingStars rating={review.rating} size="sm" />
              </div>
              {review.title ? (
                <h4 className="mt-3 text-sm font-semibold text-zinc-800">{review.title}</h4>
              ) : null}
              <p className="mt-1.5 text-sm leading-relaxed text-zinc-600">{review.comment}</p>
              {review.verified ? (
                <p className="mt-3 inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-100">
                  <span aria-hidden>✓</span> Verified purchase
                </p>
              ) : null}
            </article>
          ))
        )}
      </div>
    </div>
  );
}

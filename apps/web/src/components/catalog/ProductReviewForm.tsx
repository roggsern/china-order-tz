"use client";

import { useState } from "react";
import {
  CustomerReviewApiError,
  submitProductReview,
  type SubmitProductReviewInput,
} from "@/lib/api/customer-reviews";
import { RatingStars } from "./RatingStars";

interface ProductReviewFormProps {
  productSlug: string;
  onSubmitted?: () => void;
  compact?: boolean;
}

export function ProductReviewForm({
  productSlug,
  onSubmitted,
  compact = false,
}: ProductReviewFormProps) {
  const [rating, setRating] = useState(0);
  const [hoverRating, setHoverRating] = useState(0);
  const [title, setTitle] = useState("");
  const [comment, setComment] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitted, setSubmitted] = useState(false);

  const displayRating = hoverRating || rating;

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);
    setFieldErrors({});

    const nextFieldErrors: Record<string, string> = {};

    if (rating < 1 || rating > 5) {
      nextFieldErrors.rating = "Select a rating between 1 and 5 stars.";
    }

    if (!comment.trim()) {
      nextFieldErrors.comment = "Comment is required.";
    }

    if (Object.keys(nextFieldErrors).length > 0) {
      setFieldErrors(nextFieldErrors);
      return;
    }

    const payload: SubmitProductReviewInput = {
      rating,
      comment: comment.trim(),
      ...(title.trim() ? { title: title.trim() } : {}),
    };

    setIsSubmitting(true);

    try {
      await submitProductReview(productSlug, payload);
      setSubmitted(true);
      setRating(0);
      setTitle("");
      setComment("");
      onSubmitted?.();
    } catch (submitError) {
      if (submitError instanceof CustomerReviewApiError) {
        setError(submitError.message);

        if (submitError.fieldErrors) {
          const mapped: Record<string, string> = {};
          for (const [key, messages] of Object.entries(submitError.fieldErrors)) {
            if (messages[0]?.trim()) {
              mapped[key] = messages[0].trim();
            }
          }
          setFieldErrors(mapped);
        }
      } else {
        setError("Unable to submit your review. Please try again.");
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  if (submitted) {
    return (
      <div
        className={`rounded-2xl border border-emerald-100 bg-emerald-50/80 ${
          compact ? "p-4" : "p-5"
        }`}
      >
        <p className="text-sm font-semibold text-emerald-800">Review submitted</p>
        <p className="mt-1 text-sm text-emerald-700">
          Thank you. Your review is pending moderation and will appear once approved.
        </p>
      </div>
    );
  }

  return (
    <form
      onSubmit={handleSubmit}
      className={`rounded-2xl border border-zinc-100 bg-white ${
        compact ? "space-y-3 p-4" : "space-y-4 p-5"
      }`}
    >
      <div>
        <p className="text-sm font-semibold text-zinc-900">Write a review</p>
        <p className="mt-1 text-xs text-zinc-500">
          Share your experience. Reviews are moderated before publishing.
        </p>
      </div>

      <div>
        <label className="text-xs font-semibold uppercase tracking-wide text-zinc-500">
          Rating
        </label>
        <div className="mt-2 flex items-center gap-1">
          {[1, 2, 3, 4, 5].map((value) => (
            <button
              key={value}
              type="button"
              aria-label={`Rate ${value} star${value === 1 ? "" : "s"}`}
              onMouseEnter={() => setHoverRating(value)}
              onMouseLeave={() => setHoverRating(0)}
              onClick={() => setRating(value)}
              className="rounded p-0.5 text-[#c9a227] transition hover:scale-110"
            >
              <svg
                className="h-6 w-6"
                viewBox="0 0 24 24"
                fill={value <= displayRating ? "currentColor" : "none"}
                stroke="currentColor"
                strokeWidth={1.5}
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.385a.563.563 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"
                />
              </svg>
            </button>
          ))}
        </div>
        {fieldErrors.rating ? (
          <p className="mt-1 text-xs text-red-600">{fieldErrors.rating}</p>
        ) : null}
      </div>

      <div>
        <label htmlFor="review-title" className="text-xs font-semibold uppercase tracking-wide text-zinc-500">
          Title (optional)
        </label>
        <input
          id="review-title"
          type="text"
          value={title}
          onChange={(event) => setTitle(event.target.value)}
          maxLength={200}
          className="mt-2 w-full rounded-xl border border-zinc-200 px-3 py-2 text-sm text-zinc-900 outline-none transition focus:border-[#c9a227]/50 focus:ring-2 focus:ring-[#c9a227]/20"
          placeholder="Summarize your experience"
        />
      </div>

      <div>
        <label htmlFor="review-comment" className="text-xs font-semibold uppercase tracking-wide text-zinc-500">
          Comment
        </label>
        <textarea
          id="review-comment"
          value={comment}
          onChange={(event) => setComment(event.target.value)}
          rows={compact ? 3 : 4}
          maxLength={5000}
          className="mt-2 w-full rounded-xl border border-zinc-200 px-3 py-2 text-sm text-zinc-900 outline-none transition focus:border-[#c9a227]/50 focus:ring-2 focus:ring-[#c9a227]/20"
          placeholder="What did you like or dislike?"
        />
        {fieldErrors.comment ? (
          <p className="mt-1 text-xs text-red-600">{fieldErrors.comment}</p>
        ) : null}
      </div>

      {error ? <p className="text-sm text-red-600">{error}</p> : null}

      <button
        type="submit"
        disabled={isSubmitting}
        className="inline-flex w-full items-center justify-center rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
      >
        {isSubmitting ? "Submitting..." : "Submit review"}
      </button>
    </form>
  );
}

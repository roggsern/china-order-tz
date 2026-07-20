import { StarIcon } from "@/components/home/icons";

interface RatingStarsProps {
  rating: number;
  size?: "sm" | "md" | "lg";
  showValue?: boolean;
  reviewCount?: number;
  compactReviews?: boolean;
}

export function RatingStars({
  rating,
  size = "sm",
  showValue = false,
  reviewCount,
  compactReviews = false,
}: RatingStarsProps) {
  const starSize =
    size === "lg"
      ? "h-5 w-5"
      : size === "md"
        ? "h-4 w-4"
        : "h-3.5 w-3.5 sm:h-4 sm:w-4";

  return (
    <div className="flex items-center gap-1.5 sm:gap-2">
      <div className="flex items-center gap-0.5" aria-hidden="true">
        {[1, 2, 3, 4, 5].map((star) => (
          <StarIcon
            key={star}
            className={`${starSize} transition-colors ${
              star <= Math.round(rating) ? "text-[#c9a227]" : "text-zinc-200"
            }`}
            filled={star <= Math.round(rating)}
          />
        ))}
      </div>
      {showValue && (
        <span className="text-[11px] leading-none text-zinc-500 sm:text-xs">
          <span className="font-bold text-zinc-800">{rating.toFixed(1)}</span>
          {reviewCount !== undefined && (
            <span className="text-zinc-400">
              {compactReviews
                ? ` · ${reviewCount.toLocaleString()}`
                : ` (${reviewCount.toLocaleString()} reviews)`}
            </span>
          )}
        </span>
      )}
    </div>
  );
}

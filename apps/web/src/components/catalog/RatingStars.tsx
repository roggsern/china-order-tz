import { StarIcon } from "@/components/home/icons";

interface RatingStarsProps {
  rating: number;
  size?: "sm" | "md";
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
  const starSize = size === "sm" ? "h-3 w-3 sm:h-3.5 sm:w-3.5" : "h-4 w-4";

  return (
    <div className="flex items-center gap-1.5 sm:gap-2">
      <div className="flex items-center gap-0.5">
        {[1, 2, 3, 4, 5].map((star) => (
          <StarIcon
            key={star}
            className={`${starSize} ${star <= Math.round(rating) ? "text-[#c9a227]" : "text-zinc-200"}`}
            filled={star <= Math.round(rating)}
          />
        ))}
      </div>
      {showValue && (
        <span className="text-[11px] text-zinc-500 sm:text-sm">
          <span className="font-semibold text-zinc-700">{rating.toFixed(1)}</span>
          {reviewCount !== undefined &&
            (compactReviews
              ? ` (${reviewCount.toLocaleString()})`
              : ` (${reviewCount.toLocaleString()} reviews)`)}
        </span>
      )}
    </div>
  );
}

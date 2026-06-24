import { StarIcon } from "@/components/home/icons";

interface RatingStarsProps {
  rating: number;
  size?: "sm" | "md";
  showValue?: boolean;
  reviewCount?: number;
}

export function RatingStars({ rating, size = "sm", showValue = false, reviewCount }: RatingStarsProps) {
  const starSize = size === "sm" ? "h-3.5 w-3.5" : "h-4 w-4";

  return (
    <div className="flex items-center gap-2">
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
        <span className="text-sm text-zinc-500">
          {rating}
          {reviewCount !== undefined && ` (${reviewCount.toLocaleString()} reviews)`}
        </span>
      )}
    </div>
  );
}

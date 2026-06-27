import { calculateDiscount, formatPrice } from "@/lib/catalog/utils";

interface PriceDisplayProps {
  price: number;
  oldPrice: number;
  size?: "sm" | "md" | "lg";
  showDiscount?: boolean;
  variant?: "default" | "premium";
}

export function PriceDisplay({
  price,
  oldPrice,
  size = "md",
  showDiscount = true,
  variant = "default",
}: PriceDisplayProps) {
  const discount = calculateDiscount(price, oldPrice);
  const isPremium = variant === "premium";

  const priceClasses = {
    sm: isPremium ? "text-base font-bold sm:text-lg" : "text-lg font-bold",
    md: isPremium ? "text-lg font-bold sm:text-xl" : "text-xl font-bold",
    lg: "text-3xl font-bold",
  };

  const oldPriceClasses = {
    sm: "text-xs sm:text-sm",
    md: "text-sm sm:text-base",
    lg: "text-lg",
  };

  return (
    <div className="flex flex-wrap items-center gap-1.5 sm:gap-2">
      <span
        className={`${priceClasses[size]} ${isPremium ? "text-zinc-900" : "text-red-600"}`}
      >
        {formatPrice(price)}
      </span>
      {oldPrice > price && (
        <span className={`${oldPriceClasses[size]} text-zinc-400 line-through`}>
          {formatPrice(oldPrice)}
        </span>
      )}
      {showDiscount && discount > 0 && (
        <span
          className={`rounded-full px-2 py-0.5 text-[10px] font-bold sm:text-xs ${
            isPremium
              ? "bg-[#c9a227] text-zinc-900"
              : "bg-red-600/10 text-red-600"
          }`}
        >
          -{discount}%
        </span>
      )}
    </div>
  );
}

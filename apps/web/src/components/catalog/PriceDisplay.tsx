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
    sm: isPremium ? "text-lg font-extrabold tracking-tight sm:text-xl" : "text-lg font-bold",
    md: isPremium ? "text-xl font-extrabold tracking-tight sm:text-2xl" : "text-xl font-bold",
    lg: "text-3xl font-extrabold tracking-tight",
  };

  const oldPriceClasses = {
    sm: "text-[11px] sm:text-xs",
    md: "text-xs sm:text-sm",
    lg: "text-base",
  };

  return (
    <div className="flex flex-wrap items-baseline gap-x-2 gap-y-1">
      <span
        className={`${priceClasses[size]} ${isPremium ? "text-zinc-900" : "text-red-600"}`}
      >
        {formatPrice(price)}
      </span>
      {oldPrice > price && (
        <span className={`${oldPriceClasses[size]} font-medium text-zinc-400 line-through decoration-zinc-300`}>
          {formatPrice(oldPrice)}
        </span>
      )}
      {showDiscount && discount > 0 && (
        <span
          className={`rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wide sm:text-[11px] ${
            isPremium
              ? "bg-[#c9a227]/15 text-[#8b6914] ring-1 ring-[#c9a227]/25"
              : "bg-red-600/10 text-red-600"
          }`}
        >
          Save {discount}%
        </span>
      )}
    </div>
  );
}

import { calculateDiscount, formatPrice } from "@/lib/catalog/utils";

interface PriceDisplayProps {
  price: number;
  oldPrice: number;
  size?: "sm" | "md" | "lg";
  showDiscount?: boolean;
}

export function PriceDisplay({ price, oldPrice, size = "md", showDiscount = true }: PriceDisplayProps) {
  const discount = calculateDiscount(price, oldPrice);

  const priceClasses = {
    sm: "text-lg font-bold",
    md: "text-xl font-bold",
    lg: "text-3xl font-bold",
  };

  const oldPriceClasses = {
    sm: "text-sm",
    md: "text-base",
    lg: "text-lg",
  };

  return (
    <div className="flex flex-wrap items-baseline gap-2">
      <span className={`${priceClasses[size]} text-red-600`}>{formatPrice(price)}</span>
      {oldPrice > price && (
        <span className={`${oldPriceClasses[size]} text-zinc-400 line-through`}>
          {formatPrice(oldPrice)}
        </span>
      )}
      {showDiscount && discount > 0 && (
        <span className="rounded-full bg-red-600/10 px-2.5 py-0.5 text-xs font-bold text-red-600">
          -{discount}%
        </span>
      )}
    </div>
  );
}

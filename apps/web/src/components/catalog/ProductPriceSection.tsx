import { calculateDiscount, formatPrice } from "@/lib/catalog/utils";

interface ProductPriceSectionProps {
  price: number;
  oldPrice: number;
  className?: string;
}

export function ProductPriceSection({ price, oldPrice, className = "" }: ProductPriceSectionProps) {
  const discount = calculateDiscount(price, oldPrice);
  const savings = oldPrice > price ? oldPrice - price : 0;

  return (
    <div
      className={`rounded-2xl border border-zinc-100 bg-gradient-to-br from-zinc-50/80 to-white p-5 shadow-[0_4px_24px_rgba(0,0,0,0.04)] ${className}`}
    >
      <div className="flex flex-wrap items-end gap-x-3 gap-y-1">
        <span className="text-3xl font-extrabold tracking-tight text-red-600 sm:text-4xl">
          {formatPrice(price)}
        </span>
        {oldPrice > price && (
          <span className="pb-1 text-lg font-medium text-zinc-400 line-through decoration-zinc-300">
            {formatPrice(oldPrice)}
          </span>
        )}
      </div>

      {(discount > 0 || savings > 0) && (
        <div className="mt-2 flex flex-wrap items-center gap-2">
          {discount > 0 && (
            <span className="rounded-full bg-[#c9a227]/15 px-2.5 py-1 text-xs font-bold text-[#8b6914] ring-1 ring-[#c9a227]/25">
              Save {discount}%
            </span>
          )}
          {savings > 0 && (
            <span className="text-sm font-semibold text-emerald-700">
              You save {formatPrice(savings)}
            </span>
          )}
        </div>
      )}
    </div>
  );
}

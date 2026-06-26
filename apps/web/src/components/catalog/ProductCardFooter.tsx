import type { ProductShippingContext } from "@/lib/types/catalog";
import { formatDays } from "@/lib/catalog/utils";
import { getProductCardShippingOptions } from "@/lib/catalog/delivery";

type ProductCardFooterProps = ProductShippingContext & {
  className?: string;
};

export function ProductCardFooter({
  origin,
  weightKg,
  categorySlug,
  airCost,
  seaCost,
  airDeliveryDays,
  seaDeliveryDays,
  className = "",
}: ProductCardFooterProps) {
  const { origin: originInfo, options } = getProductCardShippingOptions({
    origin,
    weightKg,
    categorySlug,
    airCost,
    seaCost,
    airDeliveryDays,
    seaDeliveryDays,
  });

  return (
    <div className={`border-t border-zinc-100 bg-zinc-50/60 px-4 py-3 sm:px-5 ${className}`}>
      <p className="flex items-center gap-1.5 text-[11px] font-semibold text-zinc-700">
        <span aria-hidden>{originInfo.flag}</span>
        {originInfo.label}
      </p>
      <div className="mt-2 grid grid-cols-2 gap-2">
        {options.map((option) => (
          <div key={option.label} className="min-w-0">
            <p className="flex items-center gap-1 truncate text-[10px] font-semibold uppercase tracking-wide text-zinc-500">
              <span aria-hidden>{option.icon}</span>
              {option.label}
            </p>
            <p className="truncate text-[11px] font-semibold text-[#8b6914]">{option.shippingCost}</p>
            <p className="truncate text-[10px] text-zinc-500">
              {formatDays(option.deliveryDays)}
            </p>
          </div>
        ))}
      </div>
    </div>
  );
}

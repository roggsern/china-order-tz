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
    <div className={`border-t border-zinc-100/90 bg-zinc-50/70 px-3 py-2.5 sm:px-4 sm:py-3 ${className}`}>
      <div className="flex items-center justify-between gap-2">
        <p className="flex min-w-0 items-center gap-1.5 truncate text-[10px] font-semibold text-zinc-600 sm:text-[11px]">
          <span aria-hidden className="shrink-0 text-sm leading-none">
            {originInfo.flag}
          </span>
          <span className="truncate">{originInfo.label}</span>
        </p>
      </div>
      <div className="mt-2 flex gap-1.5">
        {options.map((option) => (
          <div
            key={option.label}
            className="flex min-w-0 flex-1 basis-0 items-start gap-1 rounded-lg bg-white px-1.5 py-1.5 ring-1 ring-zinc-100 sm:px-2"
          >
            <span aria-hidden className="shrink-0 text-xs leading-none">
              {option.icon}
            </span>
            <div className="min-w-0 flex-1">
              <p className="whitespace-nowrap text-[9px] font-bold uppercase tracking-wide text-zinc-500 sm:text-[10px]">
                {option.label}
              </p>
              <p className="mt-0.5 whitespace-nowrap text-[9px] font-semibold leading-tight text-[#8b6914] sm:text-[10px]">
                {option.shippingCost}
              </p>
              {formatDays(option.deliveryDays) ? (
                <p className="mt-0.5 whitespace-nowrap text-[9px] font-medium text-zinc-500 sm:text-[10px]">
                  {formatDays(option.deliveryDays)}
                </p>
              ) : null}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

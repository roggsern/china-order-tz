import type { ProductShippingContext } from "@/lib/types/catalog";
import { formatPrice } from "@/lib/catalog/utils";
import { formatDays } from "@/lib/catalog/utils";
import { getProductShippingOptions } from "@/lib/catalog/delivery";

type ShippingOptionsCardProps = ProductShippingContext & {
  className?: string;
};

export function ShippingOptionsCard({
  origin,
  weightKg,
  categorySlug,
  airCost,
  seaCost,
  airDeliveryDays,
  seaDeliveryDays,
  className = "",
}: ShippingOptionsCardProps) {
  const options = getProductShippingOptions({
    origin,
    weightKg,
    categorySlug,
    airCost,
    seaCost,
    airDeliveryDays,
    seaDeliveryDays,
  });

  if (options.length === 0) {
    return null;
  }

  return (
    <div className={`rounded-2xl border border-zinc-100 bg-zinc-50/50 p-5 ${className}`}>
      <p className="text-xs font-semibold uppercase tracking-[0.15em] text-zinc-500">
        Delivery Options
      </p>

      <div className="mt-4 grid grid-cols-1 items-stretch gap-3 sm:grid-cols-2 sm:gap-4">
        {options.map((option) => {
          const isAir = option.label === "Air Freight";

          return (
            <div
              key={option.label}
              className="flex h-full min-h-[9.5rem] flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm transition hover:border-[#c9a227]/25 hover:shadow-[0_4px_20px_rgba(201,162,39,0.08)] sm:p-5"
            >
              <span
                className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-lg ${
                  isAir ? "bg-[#c9a227]/15" : "bg-zinc-100"
                }`}
                aria-hidden
              >
                {option.icon}
              </span>

              <p className="mt-3 text-sm font-semibold text-zinc-900">{option.name}</p>

              <p className="mt-2 break-words text-sm font-semibold leading-snug tabular-nums text-[#8b6914] md:text-base">
                {formatPrice(option.shippingCost)}
              </p>

              <p className="mt-1 text-xs text-zinc-500">
                {formatDays(option.deliveryDays)}
              </p>
            </div>
          );
        })}
      </div>
    </div>
  );
}

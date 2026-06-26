import type { ProductOrigin } from "@/lib/types/catalog";
import { getDeliveryOptions } from "@/lib/catalog/delivery";

interface DeliveryEstimatorProps {
  origin: ProductOrigin;
  variant?: "card" | "detail";
  className?: string;
}

export function DeliveryEstimator({
  origin,
  variant = "detail",
  className = "",
}: DeliveryEstimatorProps) {
  const options = getDeliveryOptions(origin);

  if (variant === "card") {
    return (
      <div className={`grid grid-cols-2 gap-2 ${className}`}>
        {options.map((option) => (
          <div
            key={option.label}
            className="rounded-xl bg-zinc-50 px-2.5 py-2 ring-1 ring-zinc-100"
          >
            <p className="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-zinc-500">
              <span aria-hidden>{option.icon}</span>
              {option.label}
            </p>
            <p className="mt-0.5 text-[11px] font-medium text-zinc-800">{option.detail}</p>
            {option.subdetail && (
              <p className="text-[10px] text-zinc-500">{option.subdetail}</p>
            )}
          </div>
        ))}
      </div>
    );
  }

  return (
    <div className={`space-y-3 ${className}`}>
      {options.map((option) => (
        <div
          key={option.label}
          className="flex items-start gap-3 rounded-2xl border border-zinc-100 bg-zinc-50/80 px-4 py-3.5 transition hover:border-[#c9a227]/20"
        >
          <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-lg shadow-sm ring-1 ring-zinc-100">
            {option.icon}
          </span>
          <div>
            <p className="text-sm font-semibold text-zinc-900">{option.label}</p>
            <p className="text-sm text-zinc-600">{option.detail}</p>
            {option.subdetail && (
              <p className="mt-0.5 text-xs font-medium text-[#8b6914]">{option.subdetail}</p>
            )}
          </div>
        </div>
      ))}
    </div>
  );
}

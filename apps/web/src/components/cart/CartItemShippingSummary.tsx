import { formatDeliveryWindow, formatPrice } from "@/lib/catalog/utils";
import { LOCAL_DELIVERY_NEGOTIATED_LABEL } from "@/lib/catalog/product-type";
import type { ShippingMethodCode } from "@/lib/shipping/types";

const METHOD_LABELS: Record<ShippingMethodCode, string> = {
  air_freight: "Air Freight",
  sea_freight: "Sea Freight",
  local_delivery: "Local Delivery",
};

const METHOD_ICONS: Record<ShippingMethodCode, string> = {
  air_freight: "✈",
  sea_freight: "🚢",
  local_delivery: "🏙",
};

interface CartItemShippingSummaryProps {
  shippingMethod: ShippingMethodCode;
  shippingCost: number | null;
  estimatedDeliveryDays: string;
  origin: "china" | "tz";
  className?: string;
}

export function CartItemShippingSummary({
  shippingMethod,
  shippingCost,
  estimatedDeliveryDays,
  origin,
  className = "",
}: CartItemShippingSummaryProps) {
  const costLabel =
    origin === "tz" || shippingCost === null
      ? LOCAL_DELIVERY_NEGOTIATED_LABEL
      : formatPrice(shippingCost);

  return (
    <div
      className={`rounded-2xl border border-[#c9a227]/20 bg-gradient-to-br from-[#c9a227]/5 to-white px-4 py-3 ${className}`}
    >
      <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-[#8b6914]">
        Shipping summary
      </p>
      <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
        <div className="min-w-0">
          <p className="text-sm font-semibold text-zinc-900">
            <span aria-hidden className="mr-1.5">
              {METHOD_ICONS[shippingMethod]}
            </span>
            {METHOD_LABELS[shippingMethod]}
          </p>
          <p className="mt-0.5 text-xs text-zinc-500">
            Est. delivery {formatDeliveryWindow(estimatedDeliveryDays)}
          </p>
        </div>
        <p className="text-sm font-bold tabular-nums text-[#8b6914]">{costLabel}</p>
      </div>
    </div>
  );
}

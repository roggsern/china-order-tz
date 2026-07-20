"use client";

import type { ProductShippingContext } from "@/lib/types/catalog";
import { formatDeliveryWindow, formatPrice } from "@/lib/catalog/utils";
import { LOCAL_DELIVERY_NEGOTIATED_LABEL } from "@/lib/catalog/product-type";
import { getProductShippingOptions } from "@/lib/catalog/delivery";
import {
  normalizeProductDetailQuantity,
  scaleUnitShippingCost,
} from "@/lib/shipping/product-detail-cost";
import type { ShippingMethodCode } from "@/lib/shipping/types";

const RECOMMENDED_FOR: Record<string, string> = {
  "Air Freight": "Best for urgent orders",
  "Sea Freight": "Best for bulk orders",
  "Local Delivery": "Best for local pickup",
};

export type ShippingMethodSelection = ShippingMethodCode;

function optionToMethod(label: string): ShippingMethodSelection {
  if (label === "Air Freight") return "air_freight";
  if (label === "Sea Freight") return "sea_freight";
  return "local_delivery";
}

function isRecommendedOption(label: string, origin: ProductShippingContext["origin"]): boolean {
  if (origin === "tz") return label === "Local Delivery";
  return label === "Air Freight";
}

interface ShippingEstimatorProps extends ProductShippingContext {
  selectedMethod: ShippingMethodSelection;
  onSelect: (method: ShippingMethodSelection) => void;
  quantity?: number;
  /** When true, hide method cards until the customer finishes configuration. */
  configurationIncomplete?: boolean;
  className?: string;
}

export function ShippingEstimator({
  origin,
  weightKg,
  categorySlug,
  airCost,
  seaCost,
  airDeliveryDays,
  seaDeliveryDays,
  selectedMethod,
  onSelect,
  quantity = 1,
  configurationIncomplete = false,
  className = "",
}: ShippingEstimatorProps) {
  const shippingContext = {
    origin,
    weightKg,
    categorySlug,
    airCost,
    seaCost,
    airDeliveryDays,
    seaDeliveryDays,
  };
  const normalizedQuantity = normalizeProductDetailQuantity(quantity);
  const options = getProductShippingOptions(shippingContext);

  if (options.length === 0) return null;

  return (
    <div className={className}>
      <div className="mb-4">
        <p className="text-xs font-semibold uppercase tracking-[0.15em] text-zinc-500">
          Shipping
        </p>
        <p className="mt-1 text-sm text-zinc-600">
          {configurationIncomplete
            ? "Shipping depends on your selected options."
            : origin === "china"
              ? "Choose Air or Sea — this updates your Order Summary."
              : "Local fulfilment options for Tanzania orders."}
        </p>
      </div>

      {configurationIncomplete ? (
        <div
          className="rounded-2xl border border-dashed border-zinc-200 bg-zinc-50/70 px-4 py-5"
          role="status"
        >
          <p className="text-sm font-medium text-zinc-600">
            Complete your selection to estimate shipping.
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          {options.map((option) => {
            const method = optionToMethod(option.label);
            const isSelected = selectedMethod === method;
            const isRecommended = isRecommendedOption(option.label, origin);
            const isAir = option.label === "Air Freight";
            const totalShippingCost = scaleUnitShippingCost(
              option.shippingCost,
              normalizedQuantity,
            );

            return (
              <button
                key={option.label}
                type="button"
                onClick={() => onSelect(method)}
                className={`group relative flex h-full min-h-[10.5rem] flex-col rounded-2xl border p-4 text-left transition-all duration-200 sm:p-5 ${
                  isSelected
                    ? "border-[#c9a227] bg-gradient-to-br from-[#c9a227]/8 to-white shadow-[0_4px_24px_rgba(201,162,39,0.15)] ring-2 ring-[#c9a227]/30"
                    : "border-zinc-200 bg-white shadow-sm hover:border-[#c9a227]/25 hover:shadow-[0_4px_20px_rgba(201,162,39,0.08)]"
                }`}
                aria-pressed={isSelected}
              >
                {isRecommended && (
                  <span className="absolute -top-2.5 right-3 rounded-full bg-[#c9a227] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-zinc-900 shadow-sm">
                    Recommended
                  </span>
                )}

                <span
                  className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-lg transition ${
                    isAir ? "bg-[#c9a227]/15" : "bg-zinc-100 group-hover:bg-[#c9a227]/10"
                  }`}
                  aria-hidden
                >
                  {option.icon}
                </span>

                <p className="mt-3 text-sm font-semibold text-zinc-900">{option.name}</p>

                <p className="mt-1.5 text-base font-bold text-[#8b6914]">
                  {totalShippingCost === null
                    ? LOCAL_DELIVERY_NEGOTIATED_LABEL
                    : formatPrice(totalShippingCost)}
                </p>

                <p className="mt-1 text-sm font-medium text-zinc-700">
                  {formatDeliveryWindow(option.deliveryDays)}
                </p>

                <p className="mt-2 text-xs text-zinc-500">
                  {RECOMMENDED_FOR[option.label] ?? "Available for this product"}
                </p>
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}

export function getDefaultShippingMethod(
  context: ProductShippingContext,
): ShippingMethodSelection {
  const options = getProductShippingOptions(context);
  const recommended = options.find((option) =>
    isRecommendedOption(option.label, context.origin),
  );
  if (recommended) return optionToMethod(recommended.label);
  if (options[0]) return optionToMethod(options[0].label);
  return context.origin === "tz" ? "local_delivery" : "air_freight";
}

export { getShippingCostForMethod } from "@/lib/shipping/product-detail-cost";

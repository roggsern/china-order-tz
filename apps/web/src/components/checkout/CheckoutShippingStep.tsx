"use client";

import { motion } from "framer-motion";
import type { CartLineItem } from "@/lib/types/cart";
import type { ShippingMethodCode } from "@/lib/shipping/types";
import { formatPrice } from "@/lib/catalog/utils";
import { LOCAL_DELIVERY_NEGOTIATED_LABEL } from "@/lib/catalog/product-type";
import { formatDeliveryEstimate, getMethodByCode } from "@/lib/shipping/engine";
import { getShippingQuote } from "@/lib/cart/shipping";

interface CheckoutShippingStepProps {
  items: CartLineItem[];
  selectedMethod: ShippingMethodCode | null;
  onSelect: (method: ShippingMethodCode) => void;
  error?: string;
}

function computeShippingTotal(items: CartLineItem[], method: ShippingMethodCode): number {
  return items.reduce((sum, item) => {
    const methodForItem = item.origin === "tz" ? "local_delivery" : method;
    return sum + getShippingQuote(item, item.quantity, methodForItem).shippingTotal;
  }, 0);
}

export function CheckoutShippingStep({
  items,
  selectedMethod,
  onSelect,
  error,
}: CheckoutShippingStepProps) {
  const chinaItems = items.filter((item) => item.origin === "china");
  const tzItems = items.filter((item) => item.origin === "tz");
  const methods: ShippingMethodCode[] = ["air_freight", "sea_freight"];

  if (chinaItems.length === 0) {
    const localMethod = getMethodByCode("local_delivery");

    return (
      <div className="rounded-2xl border border-[#c9a227]/25 bg-[#c9a227]/5 p-5">
        <div className="flex items-start gap-3">
          <span className="text-2xl">{localMethod?.icon ?? "🚚"}</span>
          <div>
            <h3 className="text-base font-bold text-zinc-900">Local Delivery</h3>
            <p className="mt-1 text-sm text-zinc-600">
              Your order ships from our Tanzania warehouse.
            </p>
            <p className="mt-2 text-sm font-semibold text-[#8b6914]">
              Est. {localMethod ? formatDeliveryEstimate("local_delivery") : "1–5 days"}
            </p>
            <p className="mt-1 text-sm font-semibold text-[#8b6914]">
              {LOCAL_DELIVERY_NEGOTIATED_LABEL}
            </p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <p className="text-sm text-zinc-600">
        Choose how your China imports should be shipped to Tanzania.
      </p>

      <div className="grid gap-3 sm:grid-cols-2">
        {methods.map((methodCode, index) => {
          const method = getMethodByCode(methodCode);
          if (!method) return null;

          const isSelected = selectedMethod === methodCode;
          const shippingTotal = computeShippingTotal(items, methodCode);

          return (
            <motion.button
              key={methodCode}
              type="button"
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.25, delay: index * 0.06 }}
              onClick={() => onSelect(methodCode)}
              className={`flex h-full flex-col rounded-2xl border p-4 text-left transition ${
                isSelected
                  ? "border-[#c9a227] bg-[#c9a227]/5 shadow-sm ring-2 ring-[#c9a227]/30"
                  : "border-zinc-200 bg-white hover:border-[#c9a227]/40"
              }`}
              aria-pressed={isSelected}
            >
              <div className="flex items-start justify-between gap-2">
                <span className="text-2xl">{method.icon}</span>
                {isSelected && (
                  <span className="rounded-full bg-[#c9a227] px-2 py-0.5 text-[10px] font-bold uppercase text-zinc-900">
                    Selected
                  </span>
                )}
              </div>

              <h3 className="mt-3 text-base font-bold text-zinc-900">{method.name}</h3>
              <p className="mt-1 text-sm text-zinc-500">{method.description}</p>

              <p className="mt-3 text-sm font-semibold text-[#8b6914]">
                Est. {formatDeliveryEstimate(methodCode)}
              </p>

              <p className="mt-2 text-lg font-bold text-zinc-900">
                {formatPrice(shippingTotal)}
              </p>

              <p className="mt-1 text-xs text-zinc-500">
                {methodCode === "air_freight" ? "Faster delivery" : "Most economical"}
              </p>
            </motion.button>
          );
        })}
      </div>

      {tzItems.length > 0 && (
        <p className="text-xs text-zinc-500">
          {tzItems.length} local item{tzItems.length === 1 ? "" : "s"} —{" "}
          {LOCAL_DELIVERY_NEGOTIATED_LABEL.toLowerCase()}.
        </p>
      )}

      {error && (
        <p role="alert" className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </p>
      )}
    </div>
  );
}

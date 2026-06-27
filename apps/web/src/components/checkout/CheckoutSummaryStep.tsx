"use client";

import type { CartLineItem } from "@/lib/types/cart";
import type { CartTotals } from "@/lib/types/cart";
import type { CheckoutFormData } from "@/lib/types/checkout";
import { formatDeliveryEstimate, getMethodByCode } from "@/lib/shipping/engine";
import { CheckoutLineItems } from "./CheckoutLineItems";
import { OrderSummaryTotals } from "@/components/cart/OrderSummaryTotals";

interface CheckoutSummaryStepProps {
  items: CartLineItem[];
  totals: CartTotals;
  form: CheckoutFormData;
  fullName: string;
}

export function CheckoutSummaryStep({ items, totals, form, fullName }: CheckoutSummaryStepProps) {
  const primaryMethod = items.find((item) => item.origin === "china")?.shippingMethod ?? "local_delivery";
  const method = getMethodByCode(primaryMethod);

  return (
    <div className="space-y-6">
      <div className="rounded-2xl border border-zinc-100 bg-zinc-50/80 p-4">
        <p className="text-xs font-semibold uppercase tracking-[0.12em] text-zinc-500">
          Delivery details
        </p>
        <p className="mt-2 text-sm font-semibold text-zinc-900">{fullName}</p>
        <p className="text-sm text-zinc-600">{form.customer.phone}</p>
        {form.customer.email && (
          <p className="text-sm text-zinc-600">{form.customer.email}</p>
        )}
        <p className="mt-2 text-sm text-zinc-600">
          {form.shippingAddress.addressLine1}, {form.shippingAddress.city},{" "}
          {form.shippingAddress.region}
        </p>
        {method && (
          <p className="mt-3 text-sm font-medium text-[#8b6914]">
            {method.icon} {method.name} · Est. {formatDeliveryEstimate(primaryMethod)}
          </p>
        )}
      </div>

      <div>
        <h3 className="text-sm font-bold uppercase tracking-[0.12em] text-zinc-500">
          Order items
        </h3>
        <div className="mt-3 rounded-2xl border border-zinc-100 bg-white p-4">
          <CheckoutLineItems items={items} />
        </div>
      </div>

      <div className="rounded-2xl border border-zinc-100 bg-white p-4">
        <OrderSummaryTotals totals={totals} hideZeroDiscount />
      </div>
    </div>
  );
}

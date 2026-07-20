"use client";

import type { CartLineItem, CartTotals } from "@/lib/types/cart";
import type { CheckoutFormData } from "@/lib/types/checkout";
import { CheckoutLineItems } from "./CheckoutLineItems";
import { CheckoutShippingSummary } from "./CheckoutShippingSummary";
import { OrderSummaryTotals } from "@/components/cart/OrderSummaryTotals";

interface CheckoutSummaryStepProps {
  items: CartLineItem[];
  totals: CartTotals;
  form: CheckoutFormData;
  fullName: string;
}

export function CheckoutSummaryStep({ items, totals, form, fullName }: CheckoutSummaryStepProps) {
  const primaryMethod =
    items.find((item) => item.origin === "china")?.shippingMethod ?? "local_delivery";
  const estimate =
    items.find((item) => item.origin === "china")?.estimatedDeliveryDays ??
    items[0]?.estimatedDeliveryDays;

  return (
    <div className="space-y-6">
      <div className="rounded-2xl border border-zinc-100 bg-zinc-50/80 p-4">
        <p className="text-xs font-semibold uppercase tracking-[0.12em] text-zinc-500">
          Delivery details
        </p>
        <p className="mt-2 text-sm font-semibold text-zinc-900">{fullName}</p>
        <p className="text-sm text-zinc-600">{form.customer.phone}</p>
        {form.customer.email ? (
          <p className="text-sm text-zinc-600">{form.customer.email}</p>
        ) : null}
        <p className="mt-2 text-sm text-zinc-600">
          {form.shippingAddress.addressLine1}, {form.shippingAddress.city},{" "}
          {form.shippingAddress.region}
        </p>
        <CheckoutShippingSummary
          method={primaryMethod}
          estimatedDelivery={estimate}
          compact
          className="mt-3"
        />
      </div>

      <div>
        <h3 className="text-sm font-bold uppercase tracking-[0.12em] text-zinc-500">Products</h3>
        <div className="mt-3 rounded-2xl border border-zinc-100 bg-white p-4">
          <CheckoutLineItems items={items} showLineShipping={false} />
        </div>
      </div>

      <div className="rounded-2xl border border-zinc-100 bg-white p-4">
        <OrderSummaryTotals totals={totals} hideZeroDiscount totalLabel="Estimated Total" />
      </div>
    </div>
  );
}

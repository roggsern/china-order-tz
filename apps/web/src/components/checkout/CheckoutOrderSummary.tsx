"use client";

import Link from "next/link";
import type { CartTotals } from "@/lib/types/cart";
import type { OrderLineItem } from "@/lib/types/order";
import type { ShippingMethodCode } from "@/lib/shipping/types";
import { CheckoutSidebarSummary } from "./CheckoutSidebarSummary";

interface CheckoutOrderSummaryProps {
  items: OrderLineItem[];
  totals: CartTotals;
  onSubmit: () => void;
  isSubmitting?: boolean;
  submitDisabled?: boolean;
  submitLabel?: string;
  submitHint?: string;
  backHref?: string;
  backLabel?: string;
  shippingMethod?: ShippingMethodCode | null;
}

export function CheckoutOrderSummary({
  items,
  totals,
  onSubmit,
  isSubmitting = false,
  submitDisabled = false,
  submitLabel = "Place Order",
  submitHint = "Review your order once more, then place securely.",
  backHref = "/checkout",
  backLabel = "← Back to checkout",
  shippingMethod,
}: CheckoutOrderSummaryProps) {
  const primaryMethod =
    shippingMethod ??
    items.find((item) => item.shipping.method === "air_freight" || item.shipping.method === "sea_freight")
      ?.shipping.method ??
    items[0]?.shipping.method ??
    null;

  return (
    <div className="space-y-4">
      <CheckoutSidebarSummary
        items={items}
        totals={totals}
        shippingMethod={primaryMethod}
        onSubmit={onSubmit}
        isSubmitting={isSubmitting}
        submitDisabled={submitDisabled}
        submitLabel={submitLabel}
        submitHint={submitHint}
        mode="frozen"
        hideSubmitOnMobile
      />
      <Link
        href={backHref}
        className={`block text-center text-sm font-medium text-[#8b6914] transition hover:text-[#c9a227] ${
          isSubmitting ? "pointer-events-none opacity-50" : ""
        }`}
      >
        {backLabel}
      </Link>
    </div>
  );
}

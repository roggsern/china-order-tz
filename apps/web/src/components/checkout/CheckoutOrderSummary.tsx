"use client";

import Link from "next/link";
import { Button } from "@/components/ui/Button";
import type { CartTotals } from "@/lib/types/cart";
import type { OrderLineItem } from "@/lib/types/order";
import { OrderSummaryTotals } from "@/components/cart/OrderSummaryTotals";
import { ShippingBreakdownList } from "@/components/shipping/ShippingQuantityBreakdown";
import { buildItemShippingBreakdownFromOrderItem } from "@/lib/shipping/smart-engine";
import { FrozenCheckoutLineItems } from "./FrozenCheckoutLineItems";
import { PaymentAmountPreview } from "./PaymentAmountPreview";
import { CheckoutTrustBadges } from "./CheckoutTrustBadges";

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
}

export function CheckoutOrderSummary({
  items,
  totals,
  onSubmit,
  isSubmitting = false,
  submitDisabled = false,
  submitLabel = "Place Secure Order",
  submitHint = "Payment integration will be activated soon.",
  backHref = "/cart",
  backLabel = "← Back to cart",
}: CheckoutOrderSummaryProps) {
  return (
    <aside
      className="lg:sticky lg:top-24 lg:max-h-[calc(100vh-6rem)] lg:self-start lg:overflow-y-auto"
      aria-label="Order summary"
    >
      <div className="rounded-3xl border border-zinc-200/80 bg-white p-5 shadow-[0_4px_24px_rgba(0,0,0,0.06)] sm:p-6">
        <h2 className="text-xs font-bold uppercase tracking-[0.14em] text-zinc-500">
          Order Summary
        </h2>
        <p className="mt-1 text-sm text-zinc-600">
          {items.length} item{items.length === 1 ? "" : "s"} · frozen shipping & totals
        </p>

        <div className="mt-5">
          <FrozenCheckoutLineItems items={items} />
        </div>

        <div className="mt-5 border-t border-zinc-100 pt-5">
          <OrderSummaryTotals totals={totals} hideZeroDiscount />
        </div>

        {items.length > 0 && (
          <div className="mt-4 rounded-xl border border-zinc-100 bg-zinc-50/80 px-3 py-3">
            <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-zinc-500">
              Shipping breakdown
            </p>
            <ShippingBreakdownList
              rows={items.map((item) => buildItemShippingBreakdownFromOrderItem(item))}
              className="mt-2"
              compact
            />
          </div>
        )}

        <div className="mt-5">
          <PaymentAmountPreview totals={totals} />
        </div>

        <Button
          type="button"
          onClick={onSubmit}
          disabled={submitDisabled || isSubmitting}
          variant="primary"
          size="lg"
          fullWidth
          className="mt-6"
        >
          {submitLabel}
        </Button>

        <CheckoutTrustBadges />

        <p className="mt-4 text-center text-[11px] leading-relaxed text-zinc-500">{submitHint}</p>

        <Link
          href={backHref}
          className={`mt-4 block text-center text-sm font-medium text-[#8b6914] transition hover:text-[#c9a227] ${isSubmitting ? "pointer-events-none opacity-50" : ""}`}
        >
          {backLabel}
        </Link>
      </div>
    </aside>
  );
}

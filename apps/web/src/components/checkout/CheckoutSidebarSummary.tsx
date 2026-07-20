"use client";

import { useEffect, useRef, useState } from "react";
import type { CartLineItem, CartTotals } from "@/lib/types/cart";
import type { OrderLineItem } from "@/lib/types/order";
import type { ShippingMethodCode } from "@/lib/shipping/types";
import { Button } from "@/components/ui/Button";
import { OrderSummaryTotals } from "@/components/cart/OrderSummaryTotals";
import { CheckoutLineItems } from "./CheckoutLineItems";
import { FrozenCheckoutLineItems } from "./FrozenCheckoutLineItems";
import { CheckoutShippingSummary } from "./CheckoutShippingSummary";
import { CheckoutTrustBadges } from "./CheckoutTrustBadges";

interface CheckoutSidebarSummaryProps {
  items: CartLineItem[] | OrderLineItem[];
  totals: CartTotals;
  shippingMethod?: ShippingMethodCode | null;
  shippingEstimate?: string;
  onSubmit: () => void;
  isSubmitting?: boolean;
  submitDisabled?: boolean;
  submitLabel?: string;
  submitHint?: string;
  /** Live cart lines vs frozen order lines */
  mode?: "cart" | "frozen";
  className?: string;
  /** Hide desktop Place Order (mobile sticky handles it). */
  hideSubmitOnMobile?: boolean;
}

function isCartLine(item: CartLineItem | OrderLineItem): item is CartLineItem {
  return "unitShippingCost" in item;
}

function resolvePrimaryShipping(
  items: Array<CartLineItem | OrderLineItem>,
  preferred?: ShippingMethodCode | null,
): { method: ShippingMethodCode | null; estimate?: string } {
  if (preferred) {
    const match = items.find((item) =>
      isCartLine(item)
        ? item.shippingMethod === preferred
        : item.shipping.method === preferred || item.shippingMethod === preferred,
    );
    return {
      method: preferred,
      estimate: match
        ? isCartLine(match)
          ? match.estimatedDeliveryDays
          : match.shipping.days || match.estimatedDeliveryDays
        : undefined,
    };
  }

  const china = items.find((item) => {
    const origin = item.origin ?? (isCartLine(item) ? undefined : undefined);
    if (origin === "china") return true;
    const method = isCartLine(item) ? item.shippingMethod : item.shipping.method;
    return method === "air_freight" || method === "sea_freight";
  });

  if (china) {
    return {
      method: isCartLine(china) ? china.shippingMethod : china.shipping.method,
      estimate: isCartLine(china)
        ? china.estimatedDeliveryDays
        : china.shipping.days || china.estimatedDeliveryDays,
    };
  }

  const first = items[0];
  if (!first) return { method: null };
  return {
    method: isCartLine(first) ? first.shippingMethod : first.shipping.method,
    estimate: isCartLine(first)
      ? first.estimatedDeliveryDays
      : first.shipping.days || first.estimatedDeliveryDays,
  };
}

export function CheckoutSidebarSummary({
  items,
  totals,
  shippingMethod,
  shippingEstimate,
  onSubmit,
  isSubmitting = false,
  submitDisabled = false,
  submitLabel = "Continue to Payment",
  submitHint,
  mode = "cart",
  className = "",
  hideSubmitOnMobile = true,
}: CheckoutSidebarSummaryProps) {
  const { method, estimate } = resolvePrimaryShipping(items, shippingMethod);
  const [pulseTotal, setPulseTotal] = useState(false);
  const prevTotal = useRef(totals.grandTotal);

  useEffect(() => {
    if (prevTotal.current !== totals.grandTotal) {
      prevTotal.current = totals.grandTotal;
      setPulseTotal(true);
      const t = window.setTimeout(() => setPulseTotal(false), 450);
      return () => window.clearTimeout(t);
    }
  }, [totals.grandTotal]);

  return (
    <aside
      className={`lg:sticky lg:top-24 lg:max-h-[calc(100vh-6rem)] lg:self-start lg:overflow-y-auto ${className}`}
      aria-label="Order summary"
    >
      <div className="overflow-hidden rounded-3xl border border-[#c9a227]/25 bg-white shadow-[0_8px_40px_rgba(201,162,39,0.1)]">
        <div className="border-b border-[#c9a227]/15 bg-gradient-to-r from-[#c9a227]/10 via-white to-[#c9a227]/5 px-5 py-4 sm:px-6">
          <h2 className="text-xs font-bold uppercase tracking-[0.14em] text-[#8b6914]">
            Order Summary
          </h2>
          <p className="mt-1 text-sm text-zinc-600">
            {items.length} item{items.length === 1 ? "" : "s"} · review before placing order
          </p>
        </div>

        <div className="space-y-5 px-5 py-5 sm:px-6">
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-zinc-500">
              Products
            </p>
            <div className="mt-3">
              {mode === "frozen" ? (
                <FrozenCheckoutLineItems
                  items={items as OrderLineItem[]}
                  showLineShipping={false}
                  compact
                />
              ) : (
                <CheckoutLineItems
                  items={items as CartLineItem[]}
                  showLineShipping={false}
                  compact
                />
              )}
            </div>
          </div>

          <div>
            <p className="mb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-zinc-500">
              Shipping
            </p>
            <CheckoutShippingSummary
              method={method}
              estimatedDelivery={shippingEstimate ?? estimate}
            />
          </div>

          <div
            className={`rounded-2xl border border-zinc-100 bg-zinc-50/60 p-4 transition duration-300 ${
              pulseTotal ? "ring-2 ring-[#c9a227]/40 scale-[1.01]" : ""
            }`}
          >
            <OrderSummaryTotals
              totals={totals}
              hideZeroDiscount
              totalLabel="Estimated Total"
            />
          </div>

          <CheckoutTrustBadges />

          <div className={hideSubmitOnMobile ? "hidden lg:block" : ""}>
            <Button
              type="button"
              onClick={onSubmit}
              disabled={submitDisabled || isSubmitting}
              variant="primary"
              size="lg"
              fullWidth
              className="transition duration-200 hover:brightness-105 active:scale-[0.99]"
            >
              {isSubmitting ? "Saving…" : submitLabel}
            </Button>
            {submitHint ? (
              <p className="mt-3 text-center text-[11px] leading-relaxed text-zinc-500">
                {submitHint}
              </p>
            ) : null}
          </div>
        </div>
      </div>
    </aside>
  );
}

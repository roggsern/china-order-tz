"use client";

import type { CartTotals } from "@/lib/types/cart";
import { formatPrice } from "@/lib/catalog/utils";
import { calculateProductDetailServiceFee } from "@/lib/shipping/product-detail-cost";
import { YouSavedMessage } from "@/components/catalog/WholesalePricingMessages";
import { WholesaleUnlockedCard } from "@/components/cart/CartItemMoqHint";

interface OrderSummaryTotalsProps {
  totals: CartTotals;
  className?: string;
  hideZeroDiscount?: boolean;
  variant?: "default" | "cart";
  /** Override the final total row label (e.g. "Estimated Total"). */
  totalLabel?: string;
}

export function OrderSummaryTotals({
  totals,
  className = "",
  hideZeroDiscount = false,
  variant = "default",
  totalLabel,
}: OrderSummaryTotalsProps) {
  const isCart = variant === "cart";
  const serviceFee = isCart ? calculateProductDetailServiceFee(totals.productTotal) : 0;
  const estimatedTotal = totals.grandTotal + serviceFee;
  const finalLabel = totalLabel ?? (isCart ? "Estimated Total" : "Grand Total");
  const moqDiscount = totals.moqDiscount ?? 0;
  const originalProductTotal =
    totals.originalProductTotal ?? totals.productTotal + moqDiscount;
  const savings = totals.savings ?? moqDiscount + (totals.discount ?? 0);
  const showDiscount = !isCart && (!hideZeroDiscount || totals.discount > 0);
  const showMoqBreakdown = moqDiscount > 0;
  const unitPriceForUnlock =
    totals.itemCount > 0 ? totals.productTotal / totals.itemCount : totals.productTotal;

  return (
    <div className={className}>
      {showMoqBreakdown ? (
        <WholesaleUnlockedCard
          savingsAmount={savings}
          unitPrice={unitPriceForUnlock}
          className="mb-4"
        />
      ) : null}

      <dl className="space-y-3 text-sm">
        {showMoqBreakdown ? (
          <>
            <div className="flex items-center justify-between gap-3">
              <dt className="text-zinc-600">Products (original subtotal)</dt>
              <dd className="font-medium tabular-nums text-zinc-500 line-through">
                {formatPrice(originalProductTotal)}
              </dd>
            </div>
            <div className="flex items-center justify-between gap-3">
              <dt className="font-medium text-emerald-800">Wholesale Discount</dt>
              <dd className="font-semibold tabular-nums text-emerald-700">
                −{formatPrice(moqDiscount)}
              </dd>
            </div>
          </>
        ) : (
          <div className="flex items-center justify-between">
            <dt className="text-zinc-600">{isCart ? "Products" : "Product Total"}</dt>
            <dd className="font-semibold tabular-nums text-zinc-900">
              {formatPrice(totals.productTotal)}
            </dd>
          </div>
        )}

        <div className="flex items-center justify-between">
          <dt className="text-zinc-600">Shipping</dt>
          <dd className="font-semibold tabular-nums text-zinc-900">
            {formatPrice(totals.shippingTotal)}
          </dd>
        </div>

        {isCart && serviceFee > 0 && (
          <div className="flex items-center justify-between">
            <dt className="text-zinc-600">Service Fee</dt>
            <dd className="font-semibold tabular-nums text-zinc-900">
              {formatPrice(serviceFee)}
            </dd>
          </div>
        )}

        {showDiscount && (
          <div className="flex items-center justify-between">
            <dt className="text-zinc-600">Discount</dt>
            <dd className="font-semibold tabular-nums text-zinc-900">
              {totals.discount > 0 ? `−${formatPrice(totals.discount)}` : formatPrice(0)}
            </dd>
          </div>
        )}

        <div className={`border-t pt-3 ${isCart ? "border-[#c9a227]/15" : "border-zinc-200"}`}>
          <div className="flex items-center justify-between">
            <dt
              className={`font-semibold ${isCart ? "text-sm text-zinc-800" : "text-base text-zinc-900"}`}
            >
              {finalLabel}
            </dt>
            <dd className="text-lg font-extrabold tabular-nums tracking-tight text-zinc-900">
              {formatPrice(isCart ? estimatedTotal : totals.grandTotal)}
            </dd>
          </div>
        </div>
      </dl>

      {showMoqBreakdown ? (
        <YouSavedMessage amount={savings} className="mt-3 text-center" />
      ) : null}
    </div>
  );
}

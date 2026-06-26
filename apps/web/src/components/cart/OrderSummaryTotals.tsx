"use client";

import type { CartTotals } from "@/lib/types/cart";
import { formatPrice } from "@/lib/catalog/utils";

interface OrderSummaryTotalsProps {
  totals: CartTotals;
  className?: string;
  hideZeroDiscount?: boolean;
}

export function OrderSummaryTotals({
  totals,
  className = "",
  hideZeroDiscount = false,
}: OrderSummaryTotalsProps) {
  const showDiscount = !hideZeroDiscount || totals.discount > 0;

  return (
    <dl className={`space-y-3 text-sm ${className}`}>
      <div className="flex items-center justify-between">
        <dt className="text-zinc-600">Product Total</dt>
        <dd className="font-semibold text-zinc-900">{formatPrice(totals.productTotal)}</dd>
      </div>
      <div className="flex items-center justify-between">
        <dt className="text-zinc-600">Shipping Total</dt>
        <dd className="font-semibold text-zinc-900">{formatPrice(totals.shippingTotal)}</dd>
      </div>
      {showDiscount && (
        <div className="flex items-center justify-between">
          <dt className="text-zinc-600">Discount</dt>
          <dd className="font-semibold text-zinc-900">
            {totals.discount > 0 ? `−${formatPrice(totals.discount)}` : formatPrice(0)}
          </dd>
        </div>
      )}
      <div className="border-t border-zinc-200 pt-3">
        <div className="flex items-center justify-between">
          <dt className="text-base font-semibold text-zinc-900">Grand Total</dt>
          <dd className="text-lg font-bold text-zinc-900">{formatPrice(totals.grandTotal)}</dd>
        </div>
      </div>
    </dl>
  );
}

"use client";

import type { CartTotals } from "@/lib/types/cart";
import { OrderSummaryTotals } from "./OrderSummaryTotals";
import { ProceedToCheckoutButton } from "./ProceedToCheckoutButton";

interface OrderSummaryProps {
  totals: CartTotals;
  className?: string;
  showCheckoutAction?: boolean;
}

export function OrderSummary({
  totals,
  className = "",
  showCheckoutAction = true,
}: OrderSummaryProps) {
  return (
    <aside
      className={`h-fit lg:sticky lg:top-24 lg:max-h-[calc(100vh-6rem)] lg:overflow-y-auto ${className}`}
      aria-label="Order summary"
    >
      <div className="rounded-3xl border border-zinc-200/80 bg-white p-5 shadow-[0_4px_24px_rgba(0,0,0,0.06)] sm:p-6">
        <h2 className="text-xs font-bold uppercase tracking-[0.14em] text-zinc-500">Order Summary</h2>
        <OrderSummaryTotals totals={totals} className="mt-5" />

        {showCheckoutAction && (
          <>
            <ProceedToCheckoutButton />
            <p className="mt-3 text-center text-xs leading-relaxed text-zinc-500">
              One final payment — products + shipping
            </p>
          </>
        )}
      </div>
    </aside>
  );
}

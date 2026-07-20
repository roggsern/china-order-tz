"use client";

import Link from "next/link";
import type { CartTotals } from "@/lib/types/cart";
import { useCartActions } from "@/lib/cart/context";
import { OrderSummaryTotals } from "./OrderSummaryTotals";
import { ProceedToCheckoutButton } from "./ProceedToCheckoutButton";
import { CartTrustStrip } from "./CartTrustStrip";

interface OrderSummaryProps {
  totals: CartTotals;
  className?: string;
  showCheckoutAction?: boolean;
  showClearCart?: boolean;
}

export function OrderSummary({
  totals,
  className = "",
  showCheckoutAction = true,
  showClearCart = true,
}: OrderSummaryProps) {
  const { clearCart } = useCartActions();

  return (
    <aside
      className={`h-fit lg:sticky lg:top-24 ${className}`}
      aria-label="Order summary"
    >
      <div className="rounded-3xl border border-[#c9a227]/20 bg-gradient-to-br from-[#c9a227]/5 to-white p-5 shadow-[0_8px_40px_rgba(201,162,39,0.08)] sm:p-6">
        <p className="text-xs font-semibold uppercase tracking-[0.15em] text-[#8b6914]">
          Order Summary
        </p>
        <p className="mt-1 text-xs text-zinc-500">
          {(totals.moqDiscount ?? 0) > 0
            ? "Volume pricing is applied — see your MOQ savings below."
            : "Products, shipping, and your running cart total."}
        </p>

        <OrderSummaryTotals totals={totals} variant="cart" className="mt-5" />

        {showCheckoutAction && (
          <div className="mt-6 space-y-3">
            <ProceedToCheckoutButton className="mt-0" />
            <Link
              href="/products"
              className="flex w-full items-center justify-center rounded-xl border-2 border-zinc-200 bg-white py-3 text-sm font-semibold text-zinc-800 transition hover:border-[#c9a227]/40 hover:bg-[#c9a227]/5"
            >
              Continue Shopping
            </Link>
            {showClearCart && (
              <button
                type="button"
                onClick={() => clearCart()}
                className="flex w-full items-center justify-center rounded-xl py-2.5 text-sm font-semibold text-zinc-500 transition hover:bg-zinc-50 hover:text-red-600"
              >
                Clear cart
              </button>
            )}
            <CartTrustStrip className="pt-2" />
          </div>
        )}
      </div>
    </aside>
  );
}

"use client";

import Link from "next/link";
import { useCartState } from "@/lib/cart/context";
import { formatPrice } from "@/lib/catalog/utils";
import { ProceedToCheckoutButton } from "./ProceedToCheckoutButton";

export function CartMobileStickyCheckout() {
  const { items, totals, isHydrated } = useCartState();

  if (!isHydrated || items.length === 0) return null;

  return (
    <div className="fixed inset-x-0 bottom-0 z-40 border-t border-zinc-100 bg-white/95 shadow-[0_-10px_36px_rgba(0,0,0,0.12)] backdrop-blur-md lg:hidden">
      <div className="flex items-center gap-3 px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
        <div className="min-w-0 flex-1">
          <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-zinc-500">
            Estimated total
          </p>
          <p className="truncate text-lg font-extrabold tabular-nums text-zinc-900">
            {formatPrice(totals.grandTotal)}
          </p>
        </div>
        <div className="flex min-w-0 flex-[1.4] flex-col gap-2">
          <ProceedToCheckoutButton className="mt-0 py-3 text-sm" />
          <Link
            href="/products"
            className="text-center text-xs font-semibold text-[#8b6914] transition hover:text-[#c9a227]"
          >
            Continue shopping
          </Link>
        </div>
      </div>
    </div>
  );
}

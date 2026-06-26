"use client";

import type { CartTotals } from "@/lib/types/cart";
import { formatPrice } from "@/lib/catalog/utils";

interface PaymentAmountPreviewProps {
  totals: CartTotals;
}

export function PaymentAmountPreview({ totals }: PaymentAmountPreviewProps) {
  return (
    <div className="relative overflow-hidden rounded-2xl border border-[#c9a227]/40 bg-gradient-to-br from-zinc-900 via-zinc-900 to-zinc-800 p-[1px] shadow-[0_8px_32px_rgba(201,162,39,0.15)]">
      <div className="relative rounded-[15px] bg-gradient-to-br from-zinc-950 to-zinc-900 px-5 py-5">
        <div
          className="pointer-events-none absolute -right-8 -top-8 h-32 w-32 rounded-full bg-[#c9a227]/10 blur-2xl"
          aria-hidden
        />
        <div
          className="pointer-events-none absolute -bottom-6 -left-6 h-24 w-24 rounded-full bg-[#e8c547]/5 blur-xl"
          aria-hidden
        />

        <div className="flex items-end justify-between gap-3">
          <div>
            <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-[#e8c547]">
              Final Amount to Pay
            </p>
            <p className="mt-1 text-xs text-zinc-400">One secure payment — all inclusive</p>
          </div>
          <p className="text-2xl font-bold tracking-tight text-[#e8c547] sm:text-3xl">
            {formatPrice(totals.grandTotal)}
          </p>
        </div>
      </div>
    </div>
  );
}

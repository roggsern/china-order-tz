"use client";

import { useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import type { CartTotals } from "@/lib/types/cart";
import { formatPrice } from "@/lib/catalog/utils";
import { Button } from "@/components/ui/Button";
import { OrderSummaryTotals } from "@/components/cart/OrderSummaryTotals";

interface CheckoutMobileStickyBarProps {
  totals: CartTotals;
  onSubmit: () => void;
  isSubmitting?: boolean;
  submitDisabled?: boolean;
  submitLabel?: string;
  itemCount?: number;
}

export function CheckoutMobileStickyBar({
  totals,
  onSubmit,
  isSubmitting = false,
  submitDisabled = false,
  submitLabel = "Continue to Payment",
  itemCount,
}: CheckoutMobileStickyBarProps) {
  const [expanded, setExpanded] = useState(false);
  const reduceMotion = useReducedMotion();

  return (
    <div className="fixed inset-x-0 bottom-0 z-40 lg:hidden">
      <AnimatePresence initial={false}>
        {expanded ? (
          <motion.div
            key="panel"
            initial={reduceMotion ? false : { opacity: 0, y: 24 }}
            animate={{ opacity: 1, y: 0 }}
            exit={reduceMotion ? undefined : { opacity: 0, y: 16 }}
            transition={{ duration: 0.22, ease: "easeOut" }}
            className="border-t border-zinc-100 bg-white/98 px-4 pb-2 pt-4 shadow-[0_-12px_40px_rgba(0,0,0,0.12)] backdrop-blur-md"
          >
            <div className="mb-3 flex items-center justify-between">
              <p className="text-xs font-bold uppercase tracking-[0.14em] text-zinc-500">
                Order summary
                {itemCount != null ? ` · ${itemCount} item${itemCount === 1 ? "" : "s"}` : ""}
              </p>
              <button
                type="button"
                onClick={() => setExpanded(false)}
                className="text-xs font-semibold text-[#8b6914]"
              >
                Hide
              </button>
            </div>
            <OrderSummaryTotals
              totals={totals}
              hideZeroDiscount
              totalLabel="Estimated Total"
            />
          </motion.div>
        ) : null}
      </AnimatePresence>

      <div className="border-t border-zinc-100 bg-white/95 px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-10px_36px_rgba(0,0,0,0.12)] backdrop-blur-md">
        <div className="flex items-center gap-3">
          <button
            type="button"
            onClick={() => setExpanded((v) => !v)}
            className="min-w-0 flex-1 text-left"
            aria-expanded={expanded}
          >
            <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-zinc-500">
              Estimated total {expanded ? "▴" : "▾"}
            </p>
            <p className="truncate text-lg font-extrabold tabular-nums text-zinc-900">
              {formatPrice(totals.grandTotal)}
            </p>
          </button>
          <Button
            type="button"
            onClick={onSubmit}
            disabled={submitDisabled || isSubmitting}
            variant="primary"
            size="lg"
            className="min-w-[9.5rem] shrink-0 transition hover:brightness-105"
          >
            {isSubmitting ? "Saving…" : submitLabel}
          </Button>
        </div>
      </div>
    </div>
  );
}

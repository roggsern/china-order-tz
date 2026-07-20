"use client";

import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import { formatPrice } from "@/lib/catalog/utils";
import type { CartMoqHint } from "@/lib/cart/quote";

interface CartItemMoqHintProps {
  hint: CartMoqHint | null;
  className?: string;
}

/**
 * Encouragement when the next volume tier is still ahead.
 */
export function CartItemMoqHint({ hint, className = "" }: CartItemMoqHintProps) {
  if (!hint || hint.remainingQuantity <= 0 || hint.totalSavings <= 0) return null;

  return (
    <div
      className={`rounded-2xl border border-emerald-200/80 bg-emerald-50 px-4 py-3 ${className}`}
      role="status"
    >
      <p className="text-sm font-semibold leading-snug text-emerald-800">
        Add {hint.remainingQuantity} more{" "}
        {hint.remainingQuantity === 1 ? "item" : "items"} to unlock wholesale pricing and
        save {formatPrice(hint.totalSavings)}.
      </p>
      <p className="mt-1 text-xs text-emerald-700">
        {formatPrice(hint.nextUnitPrice)} each at {hint.targetQuantity}+ units
      </p>
    </div>
  );
}

interface WholesaleUnlockedCardProps {
  savingsAmount: number;
  unitPrice: number;
  className?: string;
}

/**
 * Success state once the MOQ / wholesale tier has been reached.
 */
export function WholesaleUnlockedCard({
  savingsAmount,
  unitPrice,
  className = "",
}: WholesaleUnlockedCardProps) {
  if (!(savingsAmount > 0)) return null;

  return (
    <div
      className={`overflow-hidden rounded-2xl border border-emerald-300 bg-gradient-to-br from-emerald-50 via-white to-emerald-50/70 shadow-[0_4px_20px_rgba(16,185,129,0.12)] ${className}`}
      role="status"
    >
      <div className="border-b border-emerald-100 bg-emerald-100/70 px-4 py-3">
        <p className="text-base font-bold text-emerald-900">
          <span aria-hidden>🎉 </span>
          Wholesale Pricing Unlocked
        </p>
      </div>

      <div className="space-y-2.5 px-4 py-3.5">
        <p className="text-sm font-bold text-emerald-800">
          You saved {formatPrice(savingsAmount)} on this order.
        </p>

        <div>
          <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-emerald-700/80">
            Current unit price
          </p>
          <p className="mt-0.5 text-lg font-extrabold tabular-nums text-emerald-900">
            {formatPrice(unitPrice)} each
          </p>
        </div>

        <p className="text-xs font-medium text-emerald-700">
          Bulk pricing has been successfully applied.
        </p>
      </div>
    </div>
  );
}

interface MoqStatusCardProps {
  /** When set and savings applied, shows the unlocked success card. */
  unlocked?: {
    savingsAmount: number;
    unitPrice: number;
  } | null;
  /** Shown when unlocked is null / inactive. */
  hint?: CartMoqHint | null;
  className?: string;
}

/**
 * Swaps the green guidance card for the success card once MOQ is reached.
 */
export function MoqStatusCard({ unlocked, hint, className = "" }: MoqStatusCardProps) {
  const reduceMotion = useReducedMotion();
  const showUnlocked = Boolean(unlocked && unlocked.savingsAmount > 0);
  const showHint =
    !showUnlocked &&
    Boolean(hint && hint.remainingQuantity > 0 && hint.totalSavings > 0);

  if (!showUnlocked && !showHint) return null;

  return (
    <div className={className}>
      <AnimatePresence mode="wait" initial={false}>
        {showUnlocked && unlocked ? (
          <motion.div
            key="unlocked"
            initial={reduceMotion ? false : { opacity: 0, y: 8, scale: 0.98 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={reduceMotion ? undefined : { opacity: 0, y: -6, scale: 0.98 }}
            transition={{ duration: 0.28, ease: "easeOut" }}
          >
            <WholesaleUnlockedCard
              savingsAmount={unlocked.savingsAmount}
              unitPrice={unlocked.unitPrice}
            />
          </motion.div>
        ) : hint ? (
          <motion.div
            key="hint"
            initial={reduceMotion ? false : { opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            exit={reduceMotion ? undefined : { opacity: 0, y: -6 }}
            transition={{ duration: 0.22, ease: "easeOut" }}
          >
            <CartItemMoqHint hint={hint} />
          </motion.div>
        ) : null}
      </AnimatePresence>
    </div>
  );
}

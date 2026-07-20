"use client";

import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import { formatPrice } from "@/lib/catalog/utils";
import type { CartMoqHint } from "@/lib/cart/quote";

interface ProductMoqAvailableCardProps {
  hint: CartMoqHint;
  className?: string;
}

/** Shown on the PDP while quantity is still below the primary wholesale MOQ. */
export function ProductMoqAvailableCard({
  hint,
  className = "",
}: ProductMoqAvailableCardProps) {
  return (
    <div
      className={`rounded-2xl border border-amber-200/90 bg-gradient-to-br from-amber-50 via-white to-[#c9a227]/8 px-4 py-3.5 shadow-[0_4px_18px_rgba(201,162,39,0.08)] ${className}`}
      role="status"
    >
      <p className="text-sm font-bold text-[#8b6914]">
        <span aria-hidden>🟡 </span>
        Wholesale pricing available
      </p>
      <p className="mt-2 text-sm font-semibold leading-snug text-zinc-800">
        Add {hint.remainingQuantity} more{" "}
        {hint.remainingQuantity === 1 ? "item" : "items"} to unlock wholesale pricing.
      </p>
      <p className="mt-1.5 text-sm font-semibold text-emerald-700">
        Save {formatPrice(hint.totalSavings)} when you reach {hint.targetQuantity}+ units.
      </p>
    </div>
  );
}

interface ProductMoqUnlockedCardProps {
  unitPrice: number;
  savingsAmount: number;
  className?: string;
}

/** Shown on the PDP once the primary MOQ quantity has been reached. */
export function ProductMoqUnlockedCard({
  unitPrice,
  savingsAmount,
  className = "",
}: ProductMoqUnlockedCardProps) {
  if (!(savingsAmount > 0)) return null;

  return (
    <div
      className={`overflow-hidden rounded-2xl border border-emerald-300 bg-gradient-to-br from-emerald-50 via-white to-emerald-50/60 shadow-[0_4px_20px_rgba(16,185,129,0.12)] ${className}`}
      role="status"
    >
      <div className="border-b border-emerald-100 bg-emerald-100/70 px-4 py-3">
        <p className="text-sm font-bold text-emerald-900 sm:text-base">
          <span aria-hidden>✓ </span>
          Wholesale pricing unlocked
        </p>
      </div>

      <div className="space-y-3 px-4 py-3.5">
        <p className="text-sm font-medium text-emerald-800">
          You are now receiving wholesale pricing.
        </p>

        <div className="grid gap-3 sm:grid-cols-2">
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-emerald-700/80">
              Wholesale price
            </p>
            <p className="mt-0.5 text-lg font-extrabold tabular-nums text-emerald-950">
              {formatPrice(unitPrice)}
              <span className="ml-1 text-sm font-semibold text-emerald-800">per unit</span>
            </p>
          </div>
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-emerald-700/80">
              You save
            </p>
            <p className="mt-0.5 text-lg font-extrabold tabular-nums text-emerald-700">
              {formatPrice(savingsAmount)}
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}

interface ProductMoqStatusCardProps {
  unlocked?: {
    unitPrice: number;
    savingsAmount: number;
  } | null;
  hint?: CartMoqHint | null;
  className?: string;
}

/**
 * PDP locked ↔ unlocked MOQ messaging with a smooth swap.
 * Always shows either the guidance card or the success card — never an empty gap.
 */
export function ProductMoqStatusCard({
  unlocked,
  hint,
  className = "",
}: ProductMoqStatusCardProps) {
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
            initial={reduceMotion ? false : { opacity: 0, y: 10, scale: 0.98 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={reduceMotion ? undefined : { opacity: 0, y: -8, scale: 0.98 }}
            transition={{ duration: 0.3, ease: "easeOut" }}
          >
            <ProductMoqUnlockedCard
              unitPrice={unlocked.unitPrice}
              savingsAmount={unlocked.savingsAmount}
            />
          </motion.div>
        ) : hint ? (
          <motion.div
            key={`available-${hint.targetQuantity}-${hint.remainingQuantity}`}
            initial={reduceMotion ? false : { opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={reduceMotion ? undefined : { opacity: 0, y: -8 }}
            transition={{ duration: 0.24, ease: "easeOut" }}
          >
            <ProductMoqAvailableCard hint={hint} />
          </motion.div>
        ) : null}
      </AnimatePresence>
    </div>
  );
}

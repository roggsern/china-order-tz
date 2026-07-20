"use client";

import { motion, useReducedMotion } from "framer-motion";
import { MinusIcon, PlusIcon } from "@/components/home/icons";

interface CartItemQuantityControlProps {
  quantity: number;
  onChange: (quantity: number) => void;
  min?: number;
  max?: number;
  isUpdating?: boolean;
  disabled?: boolean;
}

export function CartItemQuantityControl({
  quantity,
  onChange,
  min = 1,
  max = 99,
  isUpdating = false,
  disabled = false,
}: CartItemQuantityControlProps) {
  const reduceMotion = useReducedMotion();
  const isDisabled = disabled || isUpdating;

  const decrease = () => onChange(Math.max(min, quantity - 1));
  const increase = () => onChange(Math.min(max, quantity + 1));

  return (
    <div className="inline-flex items-center overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50 shadow-sm ring-1 ring-transparent transition focus-within:border-[#c9a227]/40 focus-within:ring-[#c9a227]/20">
      <button
        type="button"
        onClick={decrease}
        disabled={isDisabled || quantity <= min}
        className="flex h-11 w-11 items-center justify-center text-zinc-700 transition hover:bg-white active:scale-95 disabled:cursor-not-allowed disabled:opacity-40"
        aria-label="Decrease quantity"
      >
        <MinusIcon className="h-4 w-4" />
      </button>

      <motion.span
        key={quantity}
        initial={reduceMotion ? false : { opacity: 0, y: 4 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.18, ease: "easeOut" }}
        className={`flex h-11 min-w-[3.25rem] items-center justify-center border-x border-zinc-200 bg-white px-2 text-sm font-bold tabular-nums text-zinc-900 ${
          isUpdating ? "opacity-60" : ""
        }`}
        aria-live="polite"
        aria-busy={isUpdating}
      >
        {quantity}
      </motion.span>

      <button
        type="button"
        onClick={increase}
        disabled={isDisabled || quantity >= max}
        className="flex h-11 w-11 items-center justify-center text-zinc-700 transition hover:bg-white active:scale-95 disabled:cursor-not-allowed disabled:opacity-40"
        aria-label="Increase quantity"
      >
        <PlusIcon className="h-4 w-4" />
      </button>
    </div>
  );
}

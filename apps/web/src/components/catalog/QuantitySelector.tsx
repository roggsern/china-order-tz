"use client";

import { MinusIcon, PlusIcon } from "@/components/home/icons";

interface QuantitySelectorProps {
  quantity: number;
  onChange: (quantity: number) => void;
  min?: number;
  max?: number;
  variant?: "default" | "mobile";
}

export function QuantitySelector({
  quantity,
  onChange,
  min = 1,
  max = 99,
  variant = "default",
}: QuantitySelectorProps) {
  const decrease = () => onChange(Math.max(min, quantity - 1));
  const increase = () => onChange(Math.min(max, quantity + 1));

  const isMobile = variant === "mobile";

  return (
    <div className={`flex items-center ${isMobile ? "justify-between" : "justify-between gap-3"}`}>
      <div>
        <span className="text-sm font-semibold text-zinc-900">Quantity</span>
        {!isMobile ? (
          <p className="text-xs text-zinc-400">Use + / − to adjust</p>
        ) : null}
      </div>
      <div
        className={`inline-flex items-center overflow-hidden ${
          isMobile
            ? "rounded-2xl border border-zinc-200 bg-white shadow-sm"
            : "rounded-2xl border border-zinc-200 bg-zinc-50"
        }`}
      >
        <button
          type="button"
          onClick={decrease}
          disabled={quantity <= min}
          className={`flex items-center justify-center text-zinc-700 transition active:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-40 ${
            isMobile ? "h-11 w-11" : "h-11 w-11 hover:bg-zinc-100"
          }`}
          aria-label="Decrease quantity"
        >
          <MinusIcon className="h-4 w-4" />
        </button>
        <span
          className={`flex items-center justify-center border-x border-zinc-200 font-bold tabular-nums text-zinc-900 ${
            isMobile ? "h-11 min-w-[3.25rem] text-base" : "h-11 min-w-[3.25rem] text-sm"
          }`}
          aria-live="polite"
        >
          {quantity}
        </span>
        <button
          type="button"
          onClick={increase}
          disabled={quantity >= max}
          className={`flex items-center justify-center text-zinc-700 transition active:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-40 ${
            isMobile ? "h-11 w-11" : "h-11 w-11 hover:bg-zinc-100"
          }`}
          aria-label="Increase quantity"
        >
          <PlusIcon className="h-4 w-4" />
        </button>
      </div>
    </div>
  );
}

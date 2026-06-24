"use client";

import { MinusIcon, PlusIcon } from "@/components/home/icons";

interface QuantitySelectorProps {
  quantity: number;
  onChange: (quantity: number) => void;
  min?: number;
  max?: number;
}

export function QuantitySelector({ quantity, onChange, min = 1, max = 99 }: QuantitySelectorProps) {
  const decrease = () => onChange(Math.max(min, quantity - 1));
  const increase = () => onChange(Math.min(max, quantity + 1));

  return (
    <div className="flex items-center gap-3">
      <span className="text-sm font-medium text-zinc-700">Quantity</span>
      <div className="flex items-center rounded-xl border border-zinc-200 bg-zinc-50">
        <button
          type="button"
          onClick={decrease}
          disabled={quantity <= min}
          className="flex h-10 w-10 items-center justify-center rounded-l-xl text-zinc-600 transition hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-40"
          aria-label="Decrease quantity"
        >
          <MinusIcon className="h-4 w-4" />
        </button>
        <span className="flex h-10 w-12 items-center justify-center text-sm font-semibold text-zinc-900">
          {quantity}
        </span>
        <button
          type="button"
          onClick={increase}
          disabled={quantity >= max}
          className="flex h-10 w-10 items-center justify-center rounded-r-xl text-zinc-600 transition hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-40"
          aria-label="Increase quantity"
        >
          <PlusIcon className="h-4 w-4" />
        </button>
      </div>
    </div>
  );
}

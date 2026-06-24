"use client";

import { CartIcon } from "@/components/home/icons";

interface AddToCartButtonProps {
  onClick?: () => void;
  disabled?: boolean;
  variant?: "card" | "detail";
  className?: string;
}

export function AddToCartButton({
  onClick,
  disabled = false,
  variant = "card",
  className = "",
}: AddToCartButtonProps) {
  const baseClasses =
    variant === "card"
      ? "inline-flex w-full items-center justify-center gap-2 rounded-xl bg-zinc-900 py-2.5 text-sm font-semibold text-white transition hover:bg-[#c9a227] hover:text-zinc-900 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-zinc-900 disabled:hover:text-white"
      : "inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-zinc-900 py-3.5 text-sm font-semibold text-white transition hover:bg-[#c9a227] hover:text-zinc-900 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-zinc-900 disabled:hover:text-white";

  return (
    <button type="button" onClick={onClick} disabled={disabled} className={`${baseClasses} ${className}`}>
      <CartIcon className="h-4 w-4" />
      {disabled ? "Out of Stock" : "Add to Cart"}
    </button>
  );
}

"use client";

interface BuyNowButtonProps {
  onClick?: () => void;
  disabled?: boolean;
  className?: string;
  label?: string;
}

export function BuyNowButton({
  onClick,
  disabled = false,
  className = "",
  label = "Buy Now",
}: BuyNowButtonProps) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      className={`inline-flex flex-1 items-center justify-center rounded-xl border-2 border-zinc-900 bg-white py-3.5 text-sm font-semibold text-zinc-900 transition hover:bg-zinc-900 hover:text-white disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-white disabled:hover:text-zinc-900 ${className}`}
    >
      {label}
    </button>
  );
}

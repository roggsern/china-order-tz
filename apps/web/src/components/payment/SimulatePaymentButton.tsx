"use client";

interface SimulatePaymentButtonProps {
  onClick: () => void;
  disabled?: boolean;
  isLoading?: boolean;
  className?: string;
}

export function SimulatePaymentButton({
  onClick,
  disabled = false,
  isLoading = false,
  className = "",
}: SimulatePaymentButtonProps) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled || isLoading}
      className={`inline-flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-[#c9a227]/50 bg-[#c9a227]/5 px-5 py-3.5 text-sm font-bold text-[#8b6914] transition hover:border-[#c9a227] hover:bg-[#c9a227]/10 disabled:cursor-not-allowed disabled:opacity-50 ${className}`}
    >
      <span aria-hidden>{isLoading ? "⏳" : "🧪"}</span>
      {isLoading ? "Simulating payment…" : "Simulate Payment"}
    </button>
  );
}

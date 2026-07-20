import { formatPrice } from "@/lib/catalog/utils";

interface WholesalePricingBadgeProps {
  className?: string;
  compact?: boolean;
}

/** Shared success badge when an MOQ / quantity tier is active. */
export function WholesalePricingBadge({
  className = "",
  compact = false,
}: WholesalePricingBadgeProps) {
  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full bg-emerald-100 font-bold text-emerald-800 ring-1 ring-emerald-200/80 ${
        compact ? "px-2 py-0.5 text-[10px]" : "px-2.5 py-1 text-[11px]"
      } ${className}`}
      role="status"
    >
      <span
        className={`inline-flex items-center justify-center rounded-full bg-emerald-600 text-white ${
          compact ? "h-3.5 w-3.5 text-[8px]" : "h-4 w-4 text-[9px]"
        }`}
        aria-hidden
      >
        ✓
      </span>
      Wholesale Pricing Applied
    </span>
  );
}

interface YouSavedMessageProps {
  amount: number;
  className?: string;
  tone?: "banner" | "text" | "pill";
}

export function YouSavedMessage({
  amount,
  className = "",
  tone = "text",
}: YouSavedMessageProps) {
  if (!(amount > 0)) return null;

  const label = `You saved ${formatPrice(amount)}`;

  if (tone === "banner") {
    return (
      <div
        className={`rounded-xl bg-emerald-600 px-3.5 py-2.5 text-sm font-bold text-white ${className}`}
        role="status"
      >
        {label}
      </div>
    );
  }

  if (tone === "pill") {
    return (
      <p
        className={`inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800 ${className}`}
        role="status"
      >
        {label}
      </p>
    );
  }

  return (
    <p className={`text-sm font-bold text-emerald-700 ${className}`} role="status">
      {label}
    </p>
  );
}

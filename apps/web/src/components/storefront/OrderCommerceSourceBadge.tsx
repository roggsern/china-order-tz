import { CountryFlag } from "@/components/storefront/CountryFlag";
import {
  commerceSourceLabel,
  normalizeCommerceSource,
} from "@/lib/storefront/navigation-policy";
import { storefrontTypography } from "@/lib/storefront/typography";

type OrderCommerceSourceBadgeProps = {
  source: string | null | undefined;
  className?: string;
};

/**
 * Visual commerce-source label for order cards.
 * Flags are decorative; text label is required for accessibility.
 */
export function OrderCommerceSourceBadge({
  source,
  className = "",
}: OrderCommerceSourceBadgeProps) {
  const label = commerceSourceLabel(source);
  const normalized = normalizeCommerceSource(source);

  if (!label || !normalized) {
    return null;
  }

  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full bg-zinc-50 px-2 py-0.5 text-zinc-700 ring-1 ring-zinc-200/80 ${storefrontTypography.badge} ${className}`}
    >
      <CountryFlag
        country={normalized === "china" ? "CN" : "TZ"}
        size={12}
        decorative
      />
      <span>{label.short}</span>
    </span>
  );
}

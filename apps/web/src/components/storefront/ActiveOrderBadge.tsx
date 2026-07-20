import { storefrontTypography } from "@/lib/storefront/typography";

type ActiveOrderBadgeProps = {
  count: number;
  className?: string;
};

/** Compact count badge for My Orders — only render when count > 0. */
export function ActiveOrderBadge({ count, className = "" }: ActiveOrderBadgeProps) {
  if (count <= 0) {
    return null;
  }

  const label = count > 99 ? "99+" : String(count);

  return (
    <span
      className={`inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-[#c9a227]/15 px-1.5 text-[#8b6914] ring-1 ring-[#c9a227]/25 ${storefrontTypography.badge} ${className}`}
      aria-label={`${count} active orders`}
    >
      {label}
    </span>
  );
}

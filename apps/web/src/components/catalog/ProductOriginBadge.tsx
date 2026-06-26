import type { ProductOrigin } from "@/lib/types/catalog";
import { getOriginLabel } from "@/lib/catalog/delivery";

interface ProductOriginBadgeProps {
  origin: ProductOrigin;
  size?: "sm" | "md";
  className?: string;
}

export function ProductOriginBadge({
  origin,
  size = "sm",
  className = "",
}: ProductOriginBadgeProps) {
  const { flag, label } = getOriginLabel(origin);
  const sizeClasses = size === "sm" ? "text-[11px] px-2.5 py-1" : "text-xs px-3 py-1.5";

  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full bg-zinc-100 font-medium text-zinc-700 ring-1 ring-zinc-200/80 ${sizeClasses} ${className}`}
    >
      <span aria-hidden>{flag}</span>
      {label}
    </span>
  );
}

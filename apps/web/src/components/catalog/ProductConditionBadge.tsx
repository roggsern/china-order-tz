import { productConditionLabel, type ProductConditionValue } from "@/lib/catalog/product-condition";

const TONE: Record<ProductConditionValue, string> = {
  BRAND_NEW: "bg-emerald-700 text-white",
  OPEN_BOX: "bg-sky-700 text-white",
  REFURBISHED: "bg-amber-700 text-white",
  USED: "bg-zinc-700 text-white",
};

interface ProductConditionBadgeProps {
  condition?: ProductConditionValue | string | null;
  label?: string | null;
  className?: string;
  size?: "sm" | "md";
}

export function ProductConditionBadge({
  condition,
  label,
  className = "",
  size = "sm",
}: ProductConditionBadgeProps) {
  if (!condition) {
    return null;
  }

  const resolvedLabel = label?.trim() || productConditionLabel(condition);
  if (!resolvedLabel) {
    return null;
  }

  const tone = TONE[condition as ProductConditionValue] ?? "bg-zinc-700 text-white";
  const sizeClass =
    size === "md"
      ? "px-3 py-1 text-[11px]"
      : "px-2.5 py-1 text-[10px]";

  return (
    <span
      className={`inline-flex items-center rounded-full font-bold uppercase tracking-[0.06em] ${tone} ${sizeClass} ${className}`}
    >
      {resolvedLabel}
    </span>
  );
}

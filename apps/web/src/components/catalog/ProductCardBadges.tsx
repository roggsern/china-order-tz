import type { ProductBadgeType } from "@/lib/types/catalog";

export type CardBadgeVariant = "new" | "hot-deal" | "discount";

export type ProductCardBadgeItem = {
  label: string;
  variant: CardBadgeVariant;
};

const HOT_DEAL_BADGES: ProductBadgeType[] = [
  "BEST PRICE",
  "TRENDING",
  "BEST SELLER",
  "LIMITED STOCK",
];

const BADGE_STYLES: Record<CardBadgeVariant, string> = {
  new: "bg-zinc-900 text-[#e8c547] ring-1 ring-[#c9a227]/30",
  "hot-deal": "bg-gradient-to-r from-[#c9a227] to-[#e8c547] text-zinc-900 shadow-sm",
  discount: "bg-zinc-900/90 text-white backdrop-blur-sm",
};

export function resolveProductCardBadges(
  badges: ProductBadgeType[],
  discount: number,
): ProductCardBadgeItem[] {
  const items: ProductCardBadgeItem[] = [];

  if (badges.includes("NEW")) {
    items.push({ label: "New", variant: "new" });
  }

  if (badges.some((badge) => HOT_DEAL_BADGES.includes(badge))) {
    items.push({ label: "Hot Deal", variant: "hot-deal" });
  }

  if (discount > 0 && !items.some((item) => item.variant === "hot-deal")) {
    items.push({ label: "Discount", variant: "discount" });
  }

  return items.slice(0, 2);
}

interface ProductCardBadgesProps {
  badges: ProductBadgeType[];
  discount: number;
  className?: string;
}

export function ProductCardBadges({ badges, discount, className = "" }: ProductCardBadgesProps) {
  const items = resolveProductCardBadges(badges, discount);
  if (items.length === 0) {
    return null;
  }

  return (
    <div className={`flex flex-wrap gap-1.5 ${className}`}>
      {items.map((item) => (
        <span
          key={`${item.variant}-${item.label}`}
          className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide sm:px-2.5 sm:py-1 ${BADGE_STYLES[item.variant]}`}
        >
          {item.label}
        </span>
      ))}
    </div>
  );
}

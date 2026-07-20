import type { ProductBadgeType } from "@/lib/types/catalog";

export type CardBadgeVariant =
  | "new"
  | "hot"
  | "best-seller"
  | "limited-offer"
  | "discount"
  | "featured"
  | "trending";

export type ProductCardBadgeItem = {
  label: string;
  variant: CardBadgeVariant;
};

const BADGE_STYLES: Record<CardBadgeVariant, string> = {
  new: "bg-zinc-900 text-[#e8c547] shadow-sm ring-1 ring-[#c9a227]/35",
  hot: "bg-rose-600 text-white shadow-sm",
  "best-seller": "bg-[#c9a227] text-zinc-900 shadow-sm",
  "limited-offer": "bg-amber-500 text-zinc-900 shadow-sm",
  discount: "bg-zinc-900 text-white shadow-sm ring-1 ring-white/10",
  featured: "bg-zinc-900 text-[#e8c547] shadow-sm ring-1 ring-[#c9a227]/35",
  trending: "bg-fuchsia-600 text-white shadow-sm",
};

export function resolveProductCardBadges(
  badges: ProductBadgeType[],
  discount: number,
): ProductCardBadgeItem[] {
  const items: ProductCardBadgeItem[] = [];
  const seen = new Set<CardBadgeVariant>();

  const push = (item: ProductCardBadgeItem) => {
    if (seen.has(item.variant) || items.length >= 3) return;
    seen.add(item.variant);
    items.push(item);
  };

  if (badges.includes("NEW")) push({ label: "New", variant: "new" });
  if (badges.includes("HOT") || badges.includes("BEST PRICE")) {
    push({ label: "Hot", variant: "hot" });
  }
  if (badges.includes("BEST SELLER")) {
    push({ label: "Best Seller", variant: "best-seller" });
  }
  if (badges.includes("LIMITED OFFER") || badges.includes("LIMITED STOCK")) {
    push({ label: "Limited Offer", variant: "limited-offer" });
  }
  if (badges.includes("FEATURED") || badges.includes("PREMIUM")) {
    push({ label: "Featured", variant: "featured" });
  }
  if (badges.includes("TRENDING")) {
    push({ label: "Trending", variant: "trending" });
  }
  if (discount > 0) {
    push({ label: `-${discount}%`, variant: "discount" });
  }

  return items.slice(0, 3);
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
    <div className={`flex flex-wrap gap-1 sm:gap-1.5 ${className}`}>
      {items.map((item) => (
        <span
          key={`${item.variant}-${item.label}`}
          className={`inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.06em] sm:px-3 sm:py-1 sm:text-[11px] ${BADGE_STYLES[item.variant]}`}
        >
          {item.label}
        </span>
      ))}
    </div>
  );
}

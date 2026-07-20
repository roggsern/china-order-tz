import type { ProductBadgeType, TrustBadgeType } from "@/lib/types/catalog";

export const BADGE_STYLES: Record<
  ProductBadgeType,
  { bg: string; text: string; ring?: string }
> = {
  NEW: { bg: "bg-zinc-900", text: "text-[#e8c547]" },
  HOT: { bg: "bg-rose-600", text: "text-white" },
  "BEST SELLER": { bg: "bg-[#c9a227]", text: "text-zinc-900" },
  TRENDING: { bg: "bg-fuchsia-600", text: "text-white" },
  FEATURED: { bg: "bg-zinc-900", text: "text-[#e8c547]", ring: "ring-1 ring-[#c9a227]/40" },
  PREMIUM: { bg: "bg-zinc-900", text: "text-[#e8c547]", ring: "ring-1 ring-[#c9a227]/40" },
  "BEST PRICE": { bg: "bg-emerald-600", text: "text-white" },
  "LIMITED OFFER": { bg: "bg-amber-500", text: "text-zinc-900" },
  VERIFIED: { bg: "bg-blue-600", text: "text-white" },
  "LIMITED STOCK": { bg: "bg-amber-600", text: "text-white" },
};

export const TRUST_BADGE_ICONS: Record<TrustBadgeType, string> = {
  "Verified Supplier": "✓",
  "Fast Shipping": "⚡",
  Premium: "★",
  "Best Seller": "🏆",
  Trending: "🔥",
};

const BADGE_ALIASES: Record<string, ProductBadgeType> = {
  new: "NEW",
  hot: "HOT",
  "best seller": "BEST SELLER",
  trending: "TRENDING",
  featured: "FEATURED",
  premium: "PREMIUM",
  "best price": "BEST PRICE",
  discount: "BEST PRICE",
  verified: "VERIFIED",
  "limited stock": "LIMITED STOCK",
  "limited offer": "LIMITED OFFER",
  "hot deal": "HOT",
  "flash sale": "LIMITED OFFER",
  "top rated": "FEATURED",
  "new arrival": "NEW",
  "bulk save": "BEST PRICE",
  "beauty deal": "BEST PRICE",
  "audio deal": "BEST PRICE",
  "kitchen essential": "BEST SELLER",
  "parent pick": "BEST SELLER",
  "creator pick": "TRENDING",
  "home set": "FEATURED",
  "home upgrade": "TRENDING",
  "office wear": "NEW",
  accessory: "TRENDING",
  "skincare tool": "TRENDING",
};

export function normalizeBadge(label: string): ProductBadgeType | null {
  const key = label.toLowerCase().replace(/^-?\d+%$/, "").trim();
  if (key.match(/^-?\d+%$/)) return "BEST PRICE";
  return BADGE_ALIASES[key] ?? null;
}

export function resolveProductBadges(badge: string, stock: number): ProductBadgeType[] {
  const badges: ProductBadgeType[] = [];
  const normalized = normalizeBadge(badge);
  if (normalized) badges.push(normalized);
  if (stock > 0 && stock <= 10 && !badges.includes("LIMITED STOCK")) {
    badges.push("LIMITED STOCK");
  }
  if (badges.length === 0) badges.push("NEW");
  return badges.slice(0, 2);
}

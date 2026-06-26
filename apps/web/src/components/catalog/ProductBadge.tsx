import type { ProductBadgeType } from "@/lib/types/catalog";
import { BADGE_STYLES } from "@/lib/catalog/badges";

interface ProductBadgeProps {
  badge: ProductBadgeType;
  className?: string;
}

export function ProductBadge({ badge, className = "" }: ProductBadgeProps) {
  const style = BADGE_STYLES[badge];

  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider backdrop-blur-sm ${style.bg} ${style.text} ${style.ring ?? ""} ${className}`}
    >
      {badge}
    </span>
  );
}

interface ProductBadgesProps {
  badges: ProductBadgeType[];
  className?: string;
}

export function ProductBadges({ badges, className = "" }: ProductBadgesProps) {
  if (badges.length === 0) return null;

  return (
    <div className={`flex flex-wrap gap-1.5 ${className}`}>
      {badges.map((badge) => (
        <ProductBadge key={badge} badge={badge} />
      ))}
    </div>
  );
}

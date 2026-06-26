import type { TrustBadgeType } from "@/lib/types/catalog";
import { TRUST_BADGE_ICONS } from "@/lib/catalog/badges";

interface TrustBadgesProps {
  badges: TrustBadgeType[];
  size?: "sm" | "md";
  className?: string;
}

export function TrustBadges({ badges, size = "sm", className = "" }: TrustBadgesProps) {
  if (badges.length === 0) return null;

  const sizeClasses = size === "sm" ? "text-[10px] px-2 py-1" : "text-xs px-2.5 py-1";

  return (
    <div className={`flex flex-wrap gap-1.5 ${className}`}>
      {badges.map((badge) => (
        <span
          key={badge}
          className={`inline-flex items-center gap-1 rounded-full bg-white font-medium text-zinc-600 ring-1 ring-zinc-200/80 ${sizeClasses}`}
        >
          <span className="text-[#c9a227]" aria-hidden>
            {TRUST_BADGE_ICONS[badge]}
          </span>
          {badge}
        </span>
      ))}
    </div>
  );
}

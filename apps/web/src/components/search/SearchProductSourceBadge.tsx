"use client";

import type { SearchSourceBadge } from "@/lib/search/search-source-badge";

interface SearchProductSourceBadgeProps {
  badge: SearchSourceBadge;
  className?: string;
}

const TONE_CLASSES: Record<SearchSourceBadge["tone"], string> = {
  china: "bg-[#c9a227]/15 text-[#8b6914] ring-[#c9a227]/30",
  dar: "bg-emerald-50 text-emerald-800 ring-emerald-200",
  brand: "bg-violet-50 text-violet-800 ring-violet-200",
};

export function SearchProductSourceBadge({ badge, className = "" }: SearchProductSourceBadgeProps) {
  return (
    <span
      className={`inline-flex shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 ${TONE_CLASSES[badge.tone]} ${className}`}
    >
      {badge.label}
    </span>
  );
}

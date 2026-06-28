import type { AdminOrderSourceBadgeData } from "@/lib/admin/order-source-badge";

interface AdminOrderSourceBadgeProps {
  badge: AdminOrderSourceBadgeData;
  className?: string;
}

const TONE_CLASSES: Record<AdminOrderSourceBadgeData["tone"], string> = {
  china: "bg-blue-50 text-blue-800 ring-blue-200",
  dar: "bg-emerald-50 text-emerald-800 ring-emerald-200",
  brand: "bg-violet-50 text-violet-800 ring-violet-200",
};

export function AdminOrderSourceBadge({ badge, className = "" }: AdminOrderSourceBadgeProps) {
  return (
    <span
      className={`inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ${TONE_CLASSES[badge.tone]} ${className}`}
    >
      {badge.label}
    </span>
  );
}

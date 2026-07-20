import type { ProductStatus } from "@/lib/types/catalog";

interface StatusBadgeProps {
  status: ProductStatus;
}

const statusStyles: Record<ProductStatus, { label: string; className: string; dot: string }> = {
  active: {
    label: "Active",
    className: "bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20",
    dot: "bg-emerald-500",
  },
  draft: {
    label: "Draft",
    className: "bg-amber-50 text-amber-700 ring-1 ring-amber-600/20",
    dot: "bg-amber-500",
  },
  hidden: {
    label: "Inactive",
    className: "bg-zinc-100 text-zinc-600 ring-1 ring-zinc-300/50",
    dot: "bg-zinc-400",
  },
  out_of_stock: {
    label: "Out of stock",
    className: "bg-orange-50 text-orange-700 ring-1 ring-orange-600/20",
    dot: "bg-orange-500",
  },
  archived: {
    label: "Archived",
    className: "bg-zinc-100 text-zinc-500 ring-1 ring-zinc-300/50",
    dot: "bg-zinc-400",
  },
};

export function StatusBadge({ status }: StatusBadgeProps) {
  const config = statusStyles[status];

  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-medium ${config.className}`}
    >
      <span className={`h-1.5 w-1.5 rounded-full ${config.dot}`} />
      {config.label}
    </span>
  );
}

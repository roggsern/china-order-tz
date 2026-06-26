import { getStockStatus } from "@/lib/catalog/utils";

interface StockStatusProps {
  stock: number;
  size?: "sm" | "md";
  className?: string;
}

const variantClasses = {
  "in-stock": "bg-emerald-50 text-emerald-700 ring-emerald-600/20",
  "low-stock": "bg-amber-50 text-amber-700 ring-amber-600/20",
  "out-of-stock": "bg-red-50 text-red-700 ring-red-600/20",
};

const dotClasses = {
  "in-stock": "bg-emerald-500",
  "low-stock": "bg-amber-500",
  "out-of-stock": "bg-red-500",
};

export function StockStatus({ stock, size = "sm", className = "" }: StockStatusProps) {
  const status = getStockStatus(stock);
  const sizeClasses = size === "sm" ? "text-[11px] px-2 py-0.5" : "text-xs px-3 py-1";

  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full font-semibold ring-1 ring-inset ${variantClasses[status.variant]} ${sizeClasses} ${className}`}
    >
      <span className={`h-1.5 w-1.5 rounded-full ${dotClasses[status.variant]}`} />
      {status.label}
    </span>
  );
}

"use client";

import { LockIcon, ShieldIcon, HeadsetIcon, PackageIcon } from "@/components/home/icons";

const TRUST_ITEMS = [
  {
    icon: LockIcon,
    label: "Secure payment",
    detail: "Encrypted checkout with trusted Tanzanian payment options",
  },
  {
    icon: ShieldIcon,
    label: "Buyer protection",
    detail: "Order support if something goes wrong in transit",
  },
  {
    icon: PackageIcon,
    label: "Tracked delivery",
    detail: "China and local fulfilment with clear shipping windows",
  },
  {
    icon: HeadsetIcon,
    label: "Customer support",
    detail: "Help with configuration, shipping, and order status",
  },
] as const;

interface ProductPurchaseTrustProps {
  className?: string;
  variant?: "default" | "compact";
}

export function ProductPurchaseTrust({
  className = "",
  variant = "default",
}: ProductPurchaseTrustProps) {
  const isCompact = variant === "compact";

  return (
    <div className={className}>
      <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-zinc-500">
        Why shop with confidence
      </p>
      <div
        className={`mt-3 grid gap-2.5 ${isCompact ? "grid-cols-1" : "grid-cols-1 sm:grid-cols-2"}`}
      >
        {TRUST_ITEMS.map((item) => {
          const Icon = item.icon;
          return (
            <div
              key={item.label}
              className="flex gap-3 rounded-2xl border border-zinc-100 bg-gradient-to-br from-zinc-50/80 to-white px-3.5 py-3 transition hover:border-[#c9a227]/25 hover:shadow-[0_6px_20px_rgba(0,0,0,0.04)]"
            >
              <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#c9a227]/12 text-[#8b6914]">
                <Icon className="h-4 w-4" />
              </span>
              <div className="min-w-0">
                <p className="text-sm font-semibold text-zinc-900">{item.label}</p>
                {!isCompact ? (
                  <p className="mt-0.5 text-xs leading-relaxed text-zinc-500">{item.detail}</p>
                ) : null}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

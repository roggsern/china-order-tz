"use client";

import { motion } from "framer-motion";
import type { ProductOrigin, TrustBadgeType } from "@/lib/types/catalog";
import { getOriginLabel } from "@/lib/catalog/delivery";

interface ProductSupplierCardProps {
  origin: ProductOrigin;
  brand?: string;
  trustBadges: TrustBadgeType[];
  rating: number;
  reviewCount: number;
}

export function ProductSupplierCard({
  origin,
  brand,
  trustBadges,
  rating,
  reviewCount,
}: ProductSupplierCardProps) {
  const originInfo = getOriginLabel(origin);
  const supplierName = brand ?? (origin === "china" ? "Verified China Supplier" : "TZ Local Partner");
  const isVerified = trustBadges.includes("Verified Supplier");

  return (
    <motion.section
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.35, delay: 0.1 }}
      className="overflow-hidden rounded-2xl border border-zinc-100 bg-white shadow-sm"
    >
      <div className="border-b border-zinc-100 bg-gradient-to-r from-zinc-50 to-white px-4 py-3">
        <p className="text-xs font-semibold uppercase tracking-[0.15em] text-zinc-500">
          Supplier Information
        </p>
      </div>

      <div className="p-4">
        <div className="flex items-start gap-3">
          <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#c9a227]/10 text-2xl">
            {originInfo.flag}
          </div>
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <h3 className="text-base font-bold text-zinc-900">{supplierName}</h3>
              {isVerified && (
                <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700">
                  Verified
                </span>
              )}
            </div>
            <p className="mt-0.5 text-sm text-zinc-500">{originInfo.label}</p>
          </div>
        </div>

        <div className="mt-4 grid grid-cols-3 gap-2">
          <Stat label="Rating" value={rating.toFixed(1)} />
          <Stat label="Reviews" value={reviewCount > 999 ? "999+" : String(reviewCount)} />
          <Stat label="Response" value="< 24h" />
        </div>

        {trustBadges.length > 0 && (
          <div className="mt-4 flex flex-wrap gap-1.5">
            {trustBadges.map((badge) => (
              <span
                key={badge}
                className="rounded-full border border-zinc-100 bg-zinc-50 px-2.5 py-1 text-[11px] font-medium text-zinc-600"
              >
                {badge}
              </span>
            ))}
          </div>
        )}
      </div>
    </motion.section>
  );
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-xl bg-zinc-50 px-2 py-2.5 text-center">
      <p className="text-sm font-bold text-zinc-900">{value}</p>
      <p className="mt-0.5 text-[10px] font-medium uppercase tracking-wide text-zinc-400">
        {label}
      </p>
    </div>
  );
}

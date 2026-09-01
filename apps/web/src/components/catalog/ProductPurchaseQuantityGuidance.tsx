"use client";

import { resolvePdpPurchaseQuantityView } from "@/lib/purchasing/purchase-quantity";
import type { PurchaseQuantityPresentation } from "@/lib/purchasing/purchase-quantity";

interface ProductPurchaseQuantityGuidanceProps {
  presentation: PurchaseQuantityPresentation | null;
  className?: string;
}

export function ProductPurchaseQuantityGuidance({
  presentation,
  className = "",
}: ProductPurchaseQuantityGuidanceProps) {
  const view = resolvePdpPurchaseQuantityView(presentation);
  if (!view) {
    return null;
  }

  return (
    <section
      className={`rounded-2xl border border-zinc-200 bg-white px-4 py-3.5 ${className}`}
      aria-label="Purchase requirements"
      aria-live="polite"
      role="status"
    >
      <p className="text-xs font-semibold uppercase tracking-[0.12em] text-zinc-500">
        Purchase requirements
      </p>
      <p className="mt-1.5 text-sm font-semibold text-zinc-900">{view.minimumLabel}</p>
      {view.incrementLabel ? (
        <p className="mt-0.5 text-sm font-medium text-zinc-800">{view.incrementLabel}</p>
      ) : null}
      {view.allowedExample ? (
        <p className="mt-1 text-xs text-zinc-500">{view.allowedExample}</p>
      ) : null}
      {view.status ? (
        <p
          className={`mt-2 text-sm font-medium ${
            view.incomplete ? "text-amber-800" : "text-zinc-600"
          }`}
        >
          {view.status}
        </p>
      ) : null}
      {view.nextAllowed ? (
        <p className="mt-1 text-sm font-medium text-amber-800">{view.nextAllowed}</p>
      ) : null}
      {view.mixVariants ? (
        <p className="mt-1.5 text-xs text-zinc-500">{view.mixVariants}</p>
      ) : null}
    </section>
  );
}

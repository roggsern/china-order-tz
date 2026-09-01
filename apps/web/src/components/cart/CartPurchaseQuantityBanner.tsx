import {
  resolveCartBlockerView,
  type PurchaseQuantityBlocker,
} from "@/lib/purchasing/purchase-quantity";

interface CartPurchaseQuantityBannerProps {
  blocker: PurchaseQuantityBlocker | null;
  aggregatesVariants?: boolean;
}

export function CartPurchaseQuantityBanner({
  blocker,
  aggregatesVariants = false,
}: CartPurchaseQuantityBannerProps) {
  if (!blocker) {
    return null;
  }

  const view = resolveCartBlockerView(blocker, aggregatesVariants);

  return (
    <div
      className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3"
      role="status"
      aria-live="polite"
      aria-label="Purchase requirements"
    >
      <p className="text-xs font-semibold uppercase tracking-[0.12em] text-amber-800">
        Purchase requirements
      </p>
      <p className="mt-1 text-sm font-semibold text-amber-950">{view.status}</p>
      {view.nextAllowed ? (
        <p className="mt-1 text-sm font-medium text-amber-900">{view.nextAllowed}</p>
      ) : null}
      {view.mixVariants ? (
        <p className="mt-1.5 text-xs text-amber-800/80">{view.mixVariants}</p>
      ) : null}
    </div>
  );
}

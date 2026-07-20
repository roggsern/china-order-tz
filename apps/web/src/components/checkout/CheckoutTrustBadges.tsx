import { HeadsetIcon, LockIcon, ShieldIcon, ShippingIcon } from "@/components/home/icons";

const TRUST_ITEMS = [
  {
    label: "Secure payment",
    icon: LockIcon,
  },
  {
    label: "Order protection",
    icon: ShieldIcon,
  },
  {
    label: "Shipping tracking included",
    icon: ShippingIcon,
  },
  {
    label: "Customer support available",
    icon: HeadsetIcon,
  },
] as const;

export function CheckoutTrustBadges() {
  return (
    <div className="rounded-2xl border border-zinc-100 bg-gradient-to-br from-zinc-50 via-white to-[#c9a227]/5 px-4 py-4">
      <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-zinc-500">
        Shop with confidence
      </p>
      <ul className="mt-3 space-y-2.5">
        {TRUST_ITEMS.map(({ label, icon: Icon }) => (
          <li key={label} className="flex items-center gap-2.5">
            <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100">
              <span className="sr-only">Check</span>
              <Icon className="h-3.5 w-3.5" aria-hidden />
            </span>
            <span className="flex items-center gap-1.5 text-xs font-semibold text-zinc-700">
              <span className="font-bold text-emerald-600" aria-hidden>
                ✓
              </span>
              {label}
            </span>
          </li>
        ))}
      </ul>
    </div>
  );
}

import { HeadsetIcon, LockIcon, ShieldIcon, ShippingIcon } from "@/components/home/icons";

const TRUST_ITEMS = [
  {
    label: "Secure Payment",
    icon: LockIcon,
  },
  {
    label: "Buyer Protection",
    icon: ShieldIcon,
  },
  {
    label: "Track Shipment",
    icon: ShippingIcon,
  },
  {
    label: "Customer Support",
    icon: HeadsetIcon,
  },
] as const;

export function CheckoutTrustBadges() {
  return (
    <div className="mt-5 grid grid-cols-2 gap-2.5">
      {TRUST_ITEMS.map(({ label, icon: Icon }) => (
        <div
          key={label}
          className="flex items-center gap-2 rounded-xl border border-zinc-200/80 bg-white px-3 py-2.5"
        >
          <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-zinc-900/5 text-[#8b6914]">
            <Icon className="h-4 w-4" />
          </span>
          <span className="text-[11px] font-semibold leading-tight text-zinc-700">{label}</span>
        </div>
      ))}
    </div>
  );
}

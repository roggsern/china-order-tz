import { LockIcon, ShieldIcon, ShippingIcon, HeadsetIcon } from "@/components/home/icons";

const ITEMS = [
  { icon: LockIcon, label: "Secure Checkout" },
  { icon: ShieldIcon, label: "Buyer Protection" },
  { icon: ShippingIcon, label: "Shipping Assurance" },
  { icon: HeadsetIcon, label: "Customer Support" },
] as const;

interface ConfidenceStripProps {
  className?: string;
  variant?: "bar" | "grid";
}

/** Subtle trust messaging for cart, PDP, and other storefront surfaces. */
export function ConfidenceStrip({ className = "", variant = "bar" }: ConfidenceStripProps) {
  if (variant === "grid") {
    return (
      <ul className={`grid grid-cols-2 gap-2 sm:grid-cols-4 ${className}`}>
        {ITEMS.map(({ icon: Icon, label }) => (
          <li
            key={label}
            className="flex items-center gap-2 rounded-xl border border-zinc-100 bg-zinc-50/80 px-3 py-2.5 text-[11px] font-semibold text-zinc-700"
          >
            <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-[#c9a227]/12 text-[#8b6914]">
              <Icon className="h-3.5 w-3.5" />
            </span>
            {label}
          </li>
        ))}
      </ul>
    );
  }

  return (
    <div
      className={`flex flex-wrap items-center justify-center gap-x-4 gap-y-2 rounded-2xl border border-zinc-100 bg-zinc-50/70 px-4 py-3 ${className}`}
    >
      {ITEMS.map(({ icon: Icon, label }) => (
        <p key={label} className="inline-flex items-center gap-1.5 text-[11px] font-semibold text-zinc-600">
          <Icon className="h-3.5 w-3.5 text-[#8b6914]" />
          {label}
        </p>
      ))}
    </div>
  );
}

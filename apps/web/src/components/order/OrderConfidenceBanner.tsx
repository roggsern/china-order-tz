import { LockIcon, ShieldIcon, BellIcon } from "@/components/home/icons";

const ITEMS = [
  { icon: LockIcon, label: "Secure payment completed" },
  { icon: ShieldIcon, label: "Your order is protected" },
  { icon: BellIcon, label: "We'll notify you about every update" },
] as const;

interface OrderConfidenceBannerProps {
  className?: string;
  tone?: "light" | "dark";
}

/** Subtle post-purchase reassurance for success and tracking pages. */
export function OrderConfidenceBanner({
  className = "",
  tone = "light",
}: OrderConfidenceBannerProps) {
  const isDark = tone === "dark";

  return (
    <ul
      className={`grid gap-2 sm:grid-cols-3 ${className}`}
      aria-label="Order confidence"
    >
      {ITEMS.map(({ icon: Icon, label }) => (
        <li
          key={label}
          className={`flex items-center gap-2.5 rounded-xl px-3.5 py-3 text-xs font-semibold leading-snug ${
            isDark
              ? "border border-zinc-800 bg-zinc-900/80 text-zinc-300"
              : "border border-[#c9a227]/15 bg-[#c9a227]/5 text-zinc-700"
          }`}
        >
          <span
            className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ${
              isDark
                ? "bg-[#c9a227]/15 text-[#e8c547]"
                : "bg-white text-[#8b6914] shadow-sm ring-1 ring-[#c9a227]/20"
            }`}
          >
            <Icon className="h-3.5 w-3.5" />
          </span>
          {label}
        </li>
      ))}
    </ul>
  );
}

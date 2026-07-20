import type { ReactNode } from "react";

interface AccountComingSoonPanelProps {
  icon: ReactNode;
  title: string;
  description: string;
  footnote?: string;
  className?: string;
}

export function AccountComingSoonPanel({
  icon,
  title,
  description,
  footnote = "Coming soon.",
  className = "",
}: AccountComingSoonPanelProps) {
  return (
    <div
      className={`relative overflow-hidden rounded-3xl border border-[#c9a227]/20 bg-gradient-to-br from-[#c9a227]/8 via-white to-zinc-50 px-6 py-14 text-center shadow-[0_8px_40px_rgba(201,162,39,0.08)] sm:px-10 sm:py-16 ${className}`}
      role="status"
    >
      <div
        className="pointer-events-none absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-[#c9a227]/15 to-transparent"
        aria-hidden
      />
      <div
        className="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-[#c9a227]/10 blur-3xl"
        aria-hidden
      />

      <div
        className="relative mx-auto flex h-20 w-20 items-center justify-center rounded-2xl border border-[#c9a227]/25 bg-white text-[#8b6914] shadow-sm"
        aria-hidden
      >
        {icon}
      </div>

      <h1 className="relative mt-6 text-2xl font-bold tracking-tight text-zinc-900">{title}</h1>
      <p className="relative mx-auto mt-3 max-w-md text-sm leading-relaxed text-zinc-500 sm:text-[0.95rem]">
        {description}
      </p>
      <p className="relative mt-5 text-sm font-semibold text-[#8b6914]">{footnote}</p>
    </div>
  );
}

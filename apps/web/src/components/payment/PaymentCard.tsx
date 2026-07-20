"use client";

import type { ReactNode } from "react";

interface PaymentCardProps {
  title: string;
  description?: string;
  icon: string;
  selected?: boolean;
  onSelect?: () => void;
  disabled?: boolean;
  children?: ReactNode;
  compact?: boolean;
  badge?: string;
}

export function PaymentCard({
  title,
  description,
  icon,
  selected = false,
  onSelect,
  disabled = false,
  children,
  compact = false,
  badge,
}: PaymentCardProps) {
  const isInteractive = Boolean(onSelect) && !disabled;

  return (
    <div className={compact ? "" : "space-y-3"}>
      <button
        type="button"
        onClick={onSelect}
        disabled={!isInteractive}
        className={`group relative w-full overflow-hidden rounded-2xl border text-left transition duration-200 ${
          selected
            ? "border-[#c9a227] bg-gradient-to-br from-[#c9a227]/12 via-white to-[#c9a227]/5 shadow-[0_8px_28px_rgba(201,162,39,0.18)] ring-2 ring-[#c9a227]/35"
            : "border-zinc-200 bg-white hover:-translate-y-0.5 hover:border-[#c9a227]/45 hover:shadow-[0_8px_24px_rgba(0,0,0,0.06)]"
        } ${isInteractive ? "cursor-pointer" : "cursor-default"} ${disabled ? "opacity-60" : ""} ${
          compact ? "p-3.5" : "p-4 sm:p-5"
        }`}
        aria-pressed={isInteractive ? selected : undefined}
      >
        {selected ? (
          <span
            className="pointer-events-none absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-[#c9a227] via-[#e8c547] to-[#c9a227]"
            aria-hidden
          />
        ) : null}

        {badge ? (
          <span className="absolute right-3 top-3 rounded-full bg-[#c9a227]/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#8b6914]">
            {badge}
          </span>
        ) : null}

        <div className="flex items-start gap-3.5">
          <span
            className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-xl transition ${
              selected
                ? "bg-[#c9a227]/20 text-zinc-900 shadow-sm"
                : "bg-zinc-100 text-zinc-700 group-hover:bg-[#c9a227]/10"
            }`}
            aria-hidden
          >
            {icon}
          </span>

          <div className="min-w-0 flex-1">
            <p className="text-sm font-bold text-zinc-900 sm:text-base">{title}</p>
            {description ? (
              <p className="mt-1 text-xs leading-relaxed text-zinc-500 sm:text-[13px]">
                {description}
              </p>
            ) : null}
          </div>

          {isInteractive ? (
            <span
              className={`mt-1 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 transition ${
                selected ? "border-[#c9a227] bg-[#c9a227]" : "border-zinc-300 bg-white"
              }`}
              aria-hidden
            >
              {selected ? (
                <svg viewBox="0 0 12 12" className="h-3 w-3 text-zinc-900" fill="currentColor">
                  <path d="M4.5 9L1.5 6l1-1 2 2 4-4 1 1-5 5z" />
                </svg>
              ) : null}
            </span>
          ) : null}
        </div>
      </button>

      {children}
    </div>
  );
}

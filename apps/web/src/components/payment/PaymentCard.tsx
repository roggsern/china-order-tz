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
        className={`group relative w-full rounded-2xl border p-4 text-left transition ${
          selected
            ? "border-[#c9a227] bg-[#c9a227]/8 shadow-[0_4px_20px_rgba(201,162,39,0.15)] ring-1 ring-[#c9a227]/30"
            : "border-zinc-200 bg-white hover:border-zinc-300 hover:bg-zinc-50/80"
        } ${isInteractive ? "cursor-pointer" : "cursor-default"} ${disabled ? "opacity-60" : ""} ${compact ? "p-3.5" : "p-4"}`}
        aria-pressed={isInteractive ? selected : undefined}
      >
        {badge && (
          <span
            className="absolute right-3 top-3 rounded-full bg-[#c9a227]/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#8b6914]"
          >
            {badge}
          </span>
        )}

        <div className="flex items-start gap-3">
          <span
            className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-xl ${
              selected
                ? "bg-[#c9a227]/20 text-zinc-900"
                : "bg-zinc-100 text-zinc-700 group-hover:bg-zinc-200/80"
            }`}
            aria-hidden
          >
            {icon}
          </span>

          <div className="min-w-0 flex-1">
            <p className="text-sm font-bold text-zinc-900">{title}</p>
            {description && (
              <p className="mt-0.5 text-xs leading-relaxed text-zinc-500">{description}</p>
            )}
          </div>

          {isInteractive && (
            <span
              className={`mt-1 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 transition ${
                selected ? "border-[#c9a227] bg-[#c9a227]" : "border-zinc-300 bg-white"
              }`}
              aria-hidden
            >
              {selected && (
                <svg viewBox="0 0 12 12" className="h-3 w-3 text-zinc-900" fill="currentColor">
                  <path d="M4.5 9L1.5 6l1-1 2 2 4-4 1 1-5 5z" />
                </svg>
              )}
            </span>
          )}
        </div>
      </button>

      {children}
    </div>
  );
}

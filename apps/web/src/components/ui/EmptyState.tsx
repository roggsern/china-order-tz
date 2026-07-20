import Link from "next/link";
import type { ReactNode } from "react";

export type EmptyStateTone = "default" | "search" | "compact";

interface EmptyStateAction {
  label: string;
  href: string;
  variant?: "primary" | "secondary";
}

interface EmptyStateProps {
  icon: ReactNode;
  title: string;
  description: string;
  primaryAction?: EmptyStateAction;
  secondaryAction?: EmptyStateAction;
  className?: string;
  tone?: EmptyStateTone;
  children?: ReactNode;
}

export function EmptyState({
  icon,
  title,
  description,
  primaryAction,
  secondaryAction,
  className = "",
  tone = "default",
  children,
}: EmptyStateProps) {
  const isCompact = tone === "compact";

  return (
    <div
      className={`relative overflow-hidden text-center ${
        tone === "search"
          ? "rounded-2xl border border-dashed border-zinc-200 bg-zinc-50/80 px-5 py-12"
          : isCompact
            ? "px-4 py-10"
            : "rounded-3xl border border-[#c9a227]/20 bg-gradient-to-br from-[#c9a227]/8 via-white to-zinc-50 px-6 py-14 shadow-[0_8px_40px_rgba(201,162,39,0.08)] sm:px-10 sm:py-16"
      } ${className}`}
      role="status"
    >
      {tone === "default" ? (
        <div
          className="pointer-events-none absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-[#c9a227]/15 to-transparent"
          aria-hidden
        />
      ) : null}

      <div
        className={`relative mx-auto flex items-center justify-center border border-[#c9a227]/25 bg-white shadow-sm ${
          isCompact || tone === "search"
            ? "h-14 w-14 rounded-2xl text-2xl"
            : "h-20 w-20 rounded-2xl text-3xl"
        }`}
        aria-hidden
      >
        {icon}
      </div>

      <h2
        className={`relative font-bold tracking-tight text-zinc-900 ${
          isCompact || tone === "search" ? "mt-4 text-lg" : "mt-6 text-2xl"
        }`}
      >
        {title}
      </h2>
      <p
        className={`relative mx-auto mt-2 max-w-md leading-relaxed text-zinc-500 ${
          isCompact || tone === "search" ? "text-sm" : "text-sm sm:text-[0.95rem]"
        }`}
      >
        {description}
      </p>

      {(primaryAction || secondaryAction) && (
        <div
          className={`relative mt-7 flex flex-col items-center gap-3 sm:flex-row sm:justify-center ${
            isCompact ? "mt-5" : ""
          }`}
        >
          {primaryAction ? (
            <Link
              href={primaryAction.href}
              className="inline-flex min-h-11 items-center justify-center rounded-xl bg-gradient-to-r from-[#c9a227] via-[#d4b83d] to-[#e8c547] px-7 py-3 text-sm font-bold tracking-wide text-zinc-900 shadow-[0_4px_16px_rgba(201,162,39,0.35)] transition duration-200 hover:brightness-105 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#c9a227] active:scale-[0.98]"
            >
              {primaryAction.label}
            </Link>
          ) : null}
          {secondaryAction ? (
            <Link
              href={secondaryAction.href}
              className="inline-flex min-h-11 items-center justify-center rounded-xl border-2 border-zinc-200 bg-white px-6 py-2.5 text-sm font-semibold text-zinc-800 transition hover:border-[#c9a227]/40 hover:bg-[#c9a227]/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#c9a227]"
            >
              {secondaryAction.label}
            </Link>
          ) : null}
        </div>
      )}

      {children ? <div className="relative mt-6">{children}</div> : null}
    </div>
  );
}

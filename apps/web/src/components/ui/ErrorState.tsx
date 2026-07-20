"use client";

import { useRouter } from "next/navigation";
import type { ReactNode } from "react";
import { toFriendlyAuthMessage } from "@/lib/auth/friendly-auth-messages";

export type ErrorStateKind = "network" | "server" | "notFound" | "generic";

interface ErrorStateProps {
  kind?: ErrorStateKind;
  title?: string;
  message?: string;
  onRetry?: () => void;
  className?: string;
  icon?: ReactNode;
}

const PRESETS: Record<
  ErrorStateKind,
  { icon: string; title: string; message: string }
> = {
  network: {
    icon: "📡",
    title: "Connection lost",
    message: "Please check your internet connection and try again.",
  },
  server: {
    icon: "⚠",
    title: "Something went wrong",
    message: "Please try again shortly.",
  },
  notFound: {
    icon: "🔍",
    title: "Nothing here",
    message: "We couldn't find what you were looking for.",
  },
  generic: {
    icon: "⚠",
    title: "Something went wrong",
    message: "Please try again shortly.",
  },
};

function detectKind(message?: string): ErrorStateKind {
  if (!message) return "generic";
  if (/network|offline|fetch failed|failed to fetch|connection/i.test(message)) {
    return "network";
  }
  if (/404|not found/i.test(message)) return "notFound";
  return "server";
}

/** Customer-friendly error panel — never shows raw technical jargon. */
export function ErrorState({
  kind,
  title,
  message,
  onRetry,
  className = "",
  icon,
}: ErrorStateProps) {
  const router = useRouter();
  const resolvedKind = kind ?? detectKind(message);
  const preset = PRESETS[resolvedKind];
  const friendly = message
    ? toFriendlyAuthMessage(message, preset.message)
    : preset.message;
  // Strip leftover technical tokens if any remain
  const safeMessage = /unauthenticated|unauthorized|forbidden|stack|exception|sql/i.test(
    friendly,
  )
    ? preset.message
    : friendly;

  const handleRetry = () => {
    if (onRetry) {
      onRetry();
      return;
    }
    router.refresh();
  };

  return (
    <div
      className={`flex flex-col items-center justify-center rounded-3xl border border-zinc-200/80 bg-gradient-to-br from-white via-white to-zinc-50 px-6 py-14 text-center shadow-[0_4px_24px_rgba(0,0,0,0.04)] ${className}`}
      role="alert"
    >
      <span
        className="flex h-16 w-16 items-center justify-center rounded-2xl border border-zinc-200 bg-white text-3xl shadow-sm"
        aria-hidden
      >
        {icon ?? preset.icon}
      </span>
      <h2 className="mt-5 text-lg font-bold tracking-tight text-zinc-900 sm:text-xl">
        {title ?? preset.title}
      </h2>
      <p className="mt-2 max-w-md text-sm leading-relaxed text-zinc-500">{safeMessage}</p>
      <button
        type="button"
        onClick={handleRetry}
        className="mt-7 inline-flex min-h-11 items-center justify-center rounded-xl bg-zinc-900 px-6 py-3 text-sm font-bold text-white transition hover:bg-[#c9a227] hover:text-zinc-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#c9a227]"
      >
        Try again
      </button>
    </div>
  );
}

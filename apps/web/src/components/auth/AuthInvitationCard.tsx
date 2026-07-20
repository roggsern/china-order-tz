import Link from "next/link";
import type { AuthInviteContext } from "@/lib/auth/friendly-auth-messages";
import { getAuthInviteCopy } from "@/lib/auth/friendly-auth-messages";
import { buildLoginHref, buildRegisterHref } from "@/lib/auth/return-url";

function InviteIcon({
  icon,
}: {
  icon: ReturnType<typeof getAuthInviteCopy>["icon"];
}) {
  const className = "h-6 w-6";
  switch (icon) {
    case "heart":
      return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.7}>
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"
          />
        </svg>
      );
    case "package":
      return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.7}>
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"
          />
        </svg>
      );
    case "star":
      return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.7}>
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"
          />
        </svg>
      );
    case "bell":
      return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.7}>
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"
          />
        </svg>
      );
    case "user":
      return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.7}>
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"
          />
        </svg>
      );
    default:
      return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.7}>
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"
          />
        </svg>
      );
  }
}

interface AuthInvitationCardProps {
  context: AuthInviteContext;
  /** Path to return to after login (e.g. /checkout). */
  returnUrl?: string;
  title?: string;
  description?: string;
  className?: string;
  compact?: boolean;
}

export function AuthInvitationCard({
  context,
  returnUrl,
  title,
  description,
  className = "",
  compact = false,
}: AuthInvitationCardProps) {
  const copy = getAuthInviteCopy(context);
  const loginHref = buildLoginHref(returnUrl);
  const registerHref = buildRegisterHref(returnUrl);

  return (
    <div
      className={`relative overflow-hidden rounded-3xl border border-[#c9a227]/25 bg-gradient-to-br from-white via-white to-[#c9a227]/8 shadow-[0_8px_40px_rgba(201,162,39,0.1)] ${
        compact ? "px-5 py-6" : "px-6 py-10 sm:px-10 sm:py-12"
      } ${className}`}
      role="status"
    >
      <div
        className="pointer-events-none absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-[#c9a227]/12 to-transparent"
        aria-hidden
      />

      <div className="relative mx-auto flex max-w-md flex-col items-center text-center">
        <span className="inline-flex h-14 w-14 items-center justify-center rounded-2xl border border-[#c9a227]/30 bg-white text-[#8b6914] shadow-sm">
          <InviteIcon icon={copy.icon} />
        </span>

        <h2 className="mt-5 text-xl font-bold tracking-tight text-zinc-900 sm:text-2xl">
          {title ?? copy.title}
        </h2>
        <p className="mt-2 text-sm leading-relaxed text-zinc-500">
          {description ?? copy.description}
        </p>

        <div className="mt-7 flex w-full flex-col gap-3 sm:flex-row sm:justify-center">
          <Link
            href={loginHref}
            className="inline-flex flex-1 items-center justify-center rounded-xl bg-gradient-to-r from-[#c9a227] via-[#d4b83d] to-[#e8c547] px-6 py-3.5 text-sm font-bold text-zinc-900 shadow-[0_4px_16px_rgba(201,162,39,0.35)] transition duration-200 hover:brightness-105 active:scale-[0.98] sm:flex-none sm:min-w-[9rem]"
          >
            Sign In
          </Link>
          <Link
            href={registerHref}
            className="inline-flex flex-1 items-center justify-center rounded-xl border-2 border-zinc-200 bg-white px-6 py-3 text-sm font-semibold text-zinc-800 transition duration-200 hover:border-[#c9a227]/40 hover:bg-[#c9a227]/5 active:scale-[0.98] sm:flex-none sm:min-w-[9rem]"
          >
            Create Account
          </Link>
        </div>
      </div>
    </div>
  );
}

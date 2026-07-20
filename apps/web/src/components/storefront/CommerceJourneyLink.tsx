"use client";

import type { ReactNode } from "react";
import Link from "next/link";
import { CountryFlag, type CountryFlagCode } from "@/components/storefront/CountryFlag";
import { storefrontTypography } from "@/lib/storefront/typography";

type CommerceJourneyLinkProps = {
  journey: "china" | "tz";
  href: string;
  label: string;
  active?: boolean;
  className?: string;
  onClick?: () => void;
  /** Optional trailing badge (e.g. active order count). */
  badge?: ReactNode;
  chevron?: ReactNode;
  asButton?: boolean;
};

const FLAG: Record<"china" | "tz", CountryFlagCode> = {
  china: "CN",
  tz: "TZ",
};

const activeUnderline =
  "relative after:absolute after:inset-x-3 after:bottom-1 after:h-[2px] after:rounded-full after:bg-[#c9a227]";

export function CommerceJourneyLink({
  journey,
  href,
  label,
  active = false,
  className = "",
  onClick,
  badge,
  chevron,
  asButton = false,
}: CommerceJourneyLinkProps) {
  const classes = [
    "inline-flex min-h-11 shrink-0 items-center gap-2 whitespace-nowrap rounded-lg px-3 py-2.5 transition-colors duration-200 ease-out hover:bg-zinc-50 hover:text-zinc-900 xl:px-4",
    storefrontTypography.navPrimary,
    active ? `${storefrontTypography.navPrimaryActive} ${activeUnderline}` : "",
    className,
  ]
    .filter(Boolean)
    .join(" ");

  const content = (
    <>
      <CountryFlag country={FLAG[journey]} size={18} decorative />
      <span>{label}</span>
      {badge}
      {chevron}
    </>
  );

  if (asButton) {
    return (
      <span className={classes} aria-current={active ? "page" : undefined}>
        {content}
      </span>
    );
  }

  return (
    <Link
      href={href}
      onClick={onClick}
      className={classes}
      aria-current={active ? "page" : undefined}
    >
      {content}
    </Link>
  );
}

"use client";

import Link from "next/link";
import { BRAND_ASSETS } from "./assets";

type PremiumBrandLogoVariant = "lockup" | "wordmark";

type PremiumBrandLogoProps = {
  variant?: PremiumBrandLogoVariant;
  className?: string;
  href?: string;
  /** Maximum display height in px. Width scales down to preserve aspect ratio. */
  height?: number;
  /** When false, renders the mark without a home link wrapper. */
  linked?: boolean;
  /** Horizontal alignment within the available width. */
  align?: "left" | "center";
};

const VARIANT_ASSETS: Record<PremiumBrandLogoVariant, string> = {
  lockup: BRAND_ASSETS.footerTransparent,
  wordmark: BRAND_ASSETS.brandingTransparent,
};

const DEFAULT_HEIGHT: Record<PremiumBrandLogoVariant, number> = {
  lockup: 64,
  wordmark: 108,
};

/**
 * Premium transparent logo for dark hero/auth surfaces.
 * Uses official assets with the dark background removed for a luxury mark presentation.
 */
export function PremiumBrandLogo({
  variant = "lockup",
  className = "",
  href = "/",
  height,
  linked = true,
  align = "left",
}: PremiumBrandLogoProps) {
  const resolvedHeight = height ?? DEFAULT_HEIGHT[variant];
  const isCentered = align === "center";

  const logo = (
    <div
      className={`relative flex max-w-full items-center ${
        isCentered ? "justify-center" : "justify-start"
      } ${className}`}
    >
      <div className="relative inline-flex max-w-full items-center justify-center px-1 py-1">
        <div
          className="pointer-events-none absolute -inset-3 rounded-full opacity-80 blur-xl sm:-inset-4 sm:opacity-90 sm:blur-2xl"
          style={{
            background:
              "radial-gradient(ellipse at center, rgba(232,197,71,0.24) 0%, rgba(201,162,39,0.08) 45%, transparent 72%)",
          }}
          aria-hidden
        />
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src={VARIANT_ASSETS[variant]}
          alt="CHINA ORDER TZ"
          className={`relative block h-auto w-auto max-w-full object-contain drop-shadow-[0_0_22px_rgba(201,162,39,0.22)] ${
            isCentered ? "object-center" : "object-left"
          }`}
          style={{ maxHeight: resolvedHeight }}
          decoding="async"
          draggable={false}
        />
      </div>
    </div>
  );

  if (!linked) {
    return logo;
  }

  return (
    <Link
      href={href}
      className={`max-w-full transition-opacity hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c9a227]/50 focus-visible:ring-offset-2 focus-visible:ring-offset-zinc-950 ${
        isCentered ? "flex w-full justify-center" : "inline-flex"
      }`}
      aria-label="CHINA ORDER TZ"
    >
      {logo}
    </Link>
  );
}

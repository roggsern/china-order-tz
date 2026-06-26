"use client";

import Link from "next/link";
import { OfficialLogoImage } from "./OfficialLogoImage";

type HorizontalBrandLogoProps = {
  className?: string;
  href?: string;
  /** header = site header (64px desktop); sm = compact (admin sidebar, 40px) */
  size?: "header" | "sm";
  /** Optional height override in px (preserves aspect ratio via width: auto) */
  height?: number;
};

const HEADER_HEIGHT = 64;
const SM_HEIGHT = 40;

export function HorizontalBrandLogo({
  className = "",
  href = "/",
  size = "header",
  height: heightOverride,
}: HorizontalBrandLogoProps) {
  const height =
    heightOverride ?? (size === "header" ? HEADER_HEIGHT : SM_HEIGHT);

  return (
    <Link
      href={href}
      className={`inline-flex shrink-0 items-center transition-opacity hover:opacity-95 ${className}`}
      aria-label="CHINA ORDER TZ"
    >
      <OfficialLogoImage variant="header" height={height} />
    </Link>
  );
}

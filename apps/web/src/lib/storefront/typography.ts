/**
 * Centralized storefront typography tokens (Tailwind class strings).
 * Prefer these over ad-hoc font sizes/weights in header and navigation.
 */

export const storefrontTypography = {
  /** Primary header nav journeys and company links — inherits --sf-font from body */
  navPrimary: "text-[13px] font-medium tracking-[0.01em] text-zinc-600 xl:text-[13.5px]",
  /** Active primary nav item */
  navPrimaryActive: "font-medium text-zinc-900",
  /** Secondary / utility header actions (search labels, account) */
  navSecondary: "text-[13px] font-medium tracking-[0.01em] text-zinc-500 xl:text-sm",
  /** Mobile drawer row */
  navMobile: "text-[15px] font-medium leading-tight text-zinc-800",
  /** Buttons / CTAs */
  button: "text-sm font-semibold tracking-[0.01em]",
  /** Section headings */
  heading: "font-bold tracking-tight text-zinc-900",
  /** Body copy */
  body: "text-sm leading-relaxed text-zinc-500",
  /** Labels and badges */
  label: "text-[11px] font-semibold tracking-[0.04em] text-zinc-600",
  badge: "text-[10px] font-semibold tracking-[0.02em]",
} as const;

export type StorefrontTypographyToken = keyof typeof storefrontTypography;

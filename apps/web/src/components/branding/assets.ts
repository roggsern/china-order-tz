/** Official CHINA ORDER TZ PNG assets — do not recreate in code. */
export const BRAND_ASSETS = {
  /** Version 1 — HEADER LOGO (HORIZONTAL), no slogan */
  header: "/branding/logo-header.png",
  /** Version 2 — icon / favicon only */
  favicon: "/branding/favicon.png",
  /** Version 3 — footer (dark background variant) */
  footer: "/branding/logo-footer.png",
  /** Version 3b — footer lockup with transparent background for dark hero surfaces */
  footerTransparent: "/branding/logo-footer-transparent.png",
  /** Version 4 — full brand lockup for branding pages only */
  branding: "/branding/logo-branding.png",
  /** Version 4b — wordmark with transparent background for dark hero surfaces */
  brandingTransparent: "/branding/logo-branding-transparent.png",
  appleTouchIcon: "/branding/apple-touch-icon.png",
} as const;

export type BrandLogoVariant = keyof typeof BRAND_ASSETS;

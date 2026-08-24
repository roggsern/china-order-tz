/**
 * Brand asset registry for mobile.
 * Official CHINA ORDER TZ lockups live in `assets/branding/`
 * (copied from `apps/web/public/branding`).
 *
 * Do not invent or generate marketing images here.
 */

export const BRAND_NAME = 'CHINA ORDER TZ';

export const BRAND_TAGLINE =
  'Import products directly from China to Tanzania';

/**
 * Runtime image modules for UI (headers, splash, marks).
 * Prefer these over ad-hoc requires in feature screens.
 */
export const brandAssetPaths = {
  /** Horizontal header lockup (no slogan). */
  logoHeader: require('../../../assets/branding/logo-header.png') as number,
  /** Footer / dark-surface lockup. */
  logoFooter: require('../../../assets/branding/logo-footer.png') as number,
  /** Compact mark for headers, avatars, and badges. */
  logoMark: require('../../../assets/branding/logo-mark.png') as number,
  /** Native splash / bootstrap splash image (canonical lockup). */
  splashBrand: require('../../../assets/branding/splash-brand.png') as number,
  /** Padded square lockup for native + React splash (do not overwrite canonical). */
  splashBrandSafe: require('../../../assets/branding/splash-brand-safe.png') as number,
  /** Store / adaptive icon source (512px). */
  appIcon: require('../../../assets/branding/app-icon.png') as number,
  /** Alias kept for older call sites. */
  splashIcon: require('../../../assets/branding/splash-brand-safe.png') as number,
  androidAdaptiveForeground: require('../../../assets/images/android-icon-foreground.png') as number,
  androidAdaptiveBackground: require('../../../assets/images/android-icon-background.png') as number,
  favicon: require('../../../assets/images/favicon.png') as number,
} as const;

/**
 * Stable on-disk paths for EAS / app.json / store tooling.
 * Keep filenames stable when refreshing assets from web.
 */
export const OFFICIAL_BRANDING_FILES = {
  logoHeader: 'assets/branding/logo-header.png',
  logoFooter: 'assets/branding/logo-footer.png',
  logoMark: 'assets/branding/logo-mark.png',
  splashBrand: 'assets/branding/splash-brand.png',
  splashBrandSafe: 'assets/branding/splash-brand-safe.png',
  appIconSource: 'assets/branding/app-icon.png',
} as const;

/** @deprecated Use OFFICIAL_BRANDING_FILES — assets are now checked in. */
export const FUTURE_BRANDING_FILES = OFFICIAL_BRANDING_FILES;

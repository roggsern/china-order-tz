/**
 * CHINA ORDER TZ mobile color tokens.
 * Aligned with web storefront brand gold (#c9a227).
 * Light-mode first; dark keys reserved for a future theme switch.
 */

export const brandColors = {
  gold: '#c9a227',
  goldLight: '#e8c547',
  goldDark: '#8b6914',
  chinaRed: '#de2910',
  tzGreen: '#1EB53A',
  tzYellow: '#FCD116',
  tzBlue: '#00A3DD',
} as const;

export const lightColors = {
  /** Primary CTA / brand accent */
  primary: brandColors.gold,
  primaryPressed: brandColors.goldDark,
  primaryMuted: '#f7f1de',
  onPrimary: '#ffffff',

  background: '#ffffff',
  backgroundMuted: '#f7f7f5',
  surface: '#ffffff',
  surfaceRaised: '#fafaf8',
  surfaceCream: '#fff8ea',

  text: '#111111',
  textSecondary: '#444444',
  textMuted: '#666666',
  textSubtle: '#888888',
  textInverse: '#ffffff',

  border: '#e5e7eb',
  borderStrong: '#cccccc',
  borderFocus: brandColors.gold,

  success: '#1b7f3a',
  successMuted: '#e8f5ec',
  error: '#b00020',
  errorMuted: '#fdecea',
  warning: '#b45309',
  warningMuted: '#fff7ed',
  info: brandColors.tzBlue,
  infoMuted: '#e6f7fb',

  overlay: 'rgba(17, 17, 17, 0.45)',
  skeleton: '#ecece8',
  skeletonHighlight: '#f5f5f2',

  /** Journey accents (flags / chips) */
  journeyChina: brandColors.chinaRed,
  journeyTz: brandColors.tzGreen,
} as const;

/** Reserved dark palette — not wired to UI yet. */
export const darkColors = {
  primary: brandColors.goldLight,
  primaryPressed: brandColors.gold,
  primaryMuted: '#3a3420',
  onPrimary: '#111111',

  background: '#0a0a0a',
  backgroundMuted: '#141414',
  surface: '#1a1a1a',
  surfaceRaised: '#222222',
  surfaceCream: '#2a2418',

  text: '#f5f5f5',
  textSecondary: '#c4c4c4',
  textMuted: '#9a9a9a',
  textSubtle: '#777777',
  textInverse: '#111111',

  border: '#2e2e2e',
  borderStrong: '#444444',
  borderFocus: brandColors.goldLight,

  success: '#3dd68c',
  successMuted: '#143526',
  error: '#ff6b6b',
  errorMuted: '#3a1518',
  warning: '#fbbf24',
  warningMuted: '#3a2a10',
  info: '#38bdf8',
  infoMuted: '#0c2a38',

  overlay: 'rgba(0, 0, 0, 0.6)',
  skeleton: '#2a2a2a',
  skeletonHighlight: '#333333',

  journeyChina: brandColors.chinaRed,
  journeyTz: brandColors.tzGreen,
} as const;

export type ThemeColors = typeof lightColors;

/** Active theme colors (light until dark mode is implemented). */
export const colors: ThemeColors = lightColors;

/** @deprecated Prefer `colors.primary` — kept only for migration notes. */
export const LEGACY_TEAL_ACCENT = '#0a7ea4';
